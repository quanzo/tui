<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\async;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredFuture;
use Revolt\EventLoop;
use quanzo\tui\classes\dto\tui\async\ShellProcessResultDto;
use quanzo\tui\interfaces\tui\async\ShellProcessRunnerInterface;

/**
 * Runner для выполнения shell-команд асинхронно (Amp v3 + Revolt EventLoop).
 *
 * Особенности:
 * - запускает `bash -lc <command>` через `proc_open()`;\n
 * - читает stdout/stderr через `EventLoop::onReadable()`;\n
 * - завершает `Future` после окончания процесса;\n
 * - поддерживает cancellation и best-effort force cancel (SIGTERM → SIGKILL).\n
 *
 * Важно:
 * - runner специально не использует `symfony/process`, чтобы не добавлять зависимость.\n
 * - буфер stdout/stderr ограничен, чтобы защититься от огромного вывода.\n
 */
final class ShellProcessRunner implements ShellProcessRunnerInterface
{
    /**
     * @var int Максимальный размер stdout+stderr в байтах (при превышении вывод будет обрезан)
     */
    private int $maxOutputBytes = 2_000_000;

    /**
     * Запускает shell-команду и возвращает объект с `Future` и `forceCancel`.
     *
     * @param string $command
     * @param Cancellation $cancellation
     * @return ShellRunningProcessDto
     */
    public function start(string $command, Cancellation $cancellation): ShellRunningProcessDto
    {
        $deferred = new DeferredFuture();

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(['bash', '-lc', $command], $descriptorSpec, $pipes);
        if (!\is_resource($process)) {
            $deferred->error(new \RuntimeException('Failed to start process'));
            return new ShellRunningProcessDto($deferred->getFuture(), static fn() => null);
        }

        /** @var resource $stdin */
        $stdin = $pipes[0];
        /** @var resource $stdout */
        $stdout = $pipes[1];
        /** @var resource $stderr */
        $stderr = $pipes[2];

        fclose($stdin);
        stream_set_blocking($stdout, false);
        stream_set_blocking($stderr, false);

        $stdoutBuf = '';
        $stderrBuf = '';
        $truncated = false;

        $status = proc_get_status($process);
        $pid = isset($status['pid']) ? (int) $status['pid'] : 0;

        $stdoutWatcher = null;
        $stderrWatcher = null;
        $pollWatcher = null;

        $cleanup = static function () use (&$stdoutWatcher, &$stderrWatcher, &$pollWatcher, $stdout, $stderr, $process): void {
            if ($stdoutWatcher !== null) {
                EventLoop::cancel($stdoutWatcher);
                $stdoutWatcher = null;
            }
            if ($stderrWatcher !== null) {
                EventLoop::cancel($stderrWatcher);
                $stderrWatcher = null;
            }
            if ($pollWatcher !== null) {
                EventLoop::cancel($pollWatcher);
                $pollWatcher = null;
            }
            @fclose($stdout);
            @fclose($stderr);
            @proc_close($process);
        };

        $append = function (string $chunk, bool $isStdout) use (&$stdoutBuf, &$stderrBuf): void {
            if ($chunk === '') {
                return;
            }
            if ($isStdout) {
                $stdoutBuf .= $chunk;
            } else {
                $stderrBuf .= $chunk;
            }
        };

        $enforceLimit = function () use (&$stdoutBuf, &$stderrBuf, &$truncated): void {
            $len = strlen($stdoutBuf) + strlen($stderrBuf);
            if ($len <= $this->maxOutputBytes) {
                return;
            }
            $truncated = true;
            $over = $len - $this->maxOutputBytes;
            // Сначала режем stdout, затем stderr (best effort).
            if ($over > 0 && $stdoutBuf !== '') {
                $cut = min($over, strlen($stdoutBuf));
                $stdoutBuf = substr($stdoutBuf, 0, max(0, strlen($stdoutBuf) - $cut));
                $over -= $cut;
            }
            if ($over > 0 && $stderrBuf !== '') {
                $cut = min($over, strlen($stderrBuf));
                $stderrBuf = substr($stderrBuf, 0, max(0, strlen($stderrBuf) - $cut));
            }
        };

        $readStream = function ($stream): string {
            $data = '';
            while (!feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $data .= $chunk;
                if (strlen($data) > 64_000) {
                    break;
                }
            }
            return $data;
        };

        $stdoutWatcher = EventLoop::onReadable($stdout, function () use ($stdout, $readStream, $append, $enforceLimit): void {
            $chunk = $readStream($stdout);
            $append($chunk, true);
            $enforceLimit();
        });

        $stderrWatcher = EventLoop::onReadable($stderr, function () use ($stderr, $readStream, $append, $enforceLimit): void {
            $chunk = $readStream($stderr);
            $append($chunk, false);
            $enforceLimit();
        });

        $forceCancel = function () use ($pid, $process): void {
            if ($pid <= 0) {
                return;
            }

            // 1) SIGTERM
            if (\function_exists('posix_kill')) {
                @posix_kill($pid, \SIGTERM);
            } else {
                @proc_terminate($process);
            }

            // 2) Через 0.5с — SIGKILL (best effort)
            EventLoop::delay(0.5, static function () use ($pid): void {
                if (\function_exists('posix_kill')) {
                    @posix_kill($pid, \SIGKILL);
                }
            });
        };

        $cancelId = $cancellation->subscribe(function (CancelledException $e) use ($deferred, $cleanup, $forceCancel): void {
            $forceCancel();
            $cleanup();
            $deferred->error($e);
        });

        $pollWatcher = EventLoop::repeat(0.05, function (string $watcherId) use (
            $process,
            $command,
            &$stdoutBuf,
            &$stderrBuf,
            &$truncated,
            $deferred,
            $cleanup,
            $cancellation,
            $cancelId,
        ): void {
            $status = proc_get_status($process);
            $running = (bool) ($status['running'] ?? false);
            if ($running) {
                return;
            }

            $cancellation->unsubscribe($cancelId);
            EventLoop::cancel($watcherId);

            $exitCode = isset($status['exitcode']) ? (int) $status['exitcode'] : 0;
            $res = new ShellProcessResultDto(
                command: $command,
                exitCode: $exitCode,
                stdout: $stdoutBuf,
                stderr: $stderrBuf,
                wasTruncated: $truncated,
            );

            $cleanup();
            $deferred->complete($res);
        });

        /** @var \Amp\Future<ShellProcessResultDto> $future */
        $future = $deferred->getFuture();
        return new ShellRunningProcessDto($future, $forceCancel);
    }
}
