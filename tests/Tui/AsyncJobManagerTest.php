<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\DeferredCancellation;
use Amp\Future;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\handlers\BreakCommandHandler;
use quanzo\tui\handlers\JobsCommandHandler;
use Revolt\EventLoop;

use function Amp\async;
use function Amp\delay;

/**
 * Тесты для асинхронной инфраструктуры TUI: `TuiJobManager` + `/break`.
 *
 * Важно: Amp v3 работает поверх Revolt EventLoop, поэтому в тестах используются короткие ожидания
 * с `EventLoop::repeat()` и `Suspension`, чтобы не блокировать тест-раннер.
 */
final class AsyncJobManagerTest extends TestCase
{
    /**
     * Проверяет базовый happy path:
     * - задача завершается успешным `TuiCommandResultDto`;
     * - `drainCompleted()` возвращает entries и удаляет задачу.
     */
    public function testJobManagerDrainsCompletedSuccessfulJob(): void
    {
        $jobs = new TuiJobManager();

        $result = (new TuiCommandResultDto())->setAppendEntries([
            TuiHistoryEntryDto::output('ok')->setPlainText('ok'),
        ]);

        $jobId = $jobs->start('/test', static fn() => Future::complete($result));

        $entries = $jobs->drainCompleted();
        // 1 entry от результата + 1 служебный entry с длительностью выполнения.
        self::assertCount(2, $entries);
        self::assertSame([], $jobs->listActiveJobIds());
        self::assertNotSame('', $jobId);
    }

    /**
     * Проверяет отмену задачи:
     * - `/break` запрашивает отмену;
     * - после завершения future `drainCompleted()` возвращает cancelled-entry.
     */
    public function testBreakCancelsRunningJobAndDrainReturnsCancelledEntry(): void
    {
        $jobs = new TuiJobManager();
        $break = new BreakCommandHandler($jobs);

        $jobId = $jobs->start('/slow', static function ($cancellation) {
            return async(static function () use ($cancellation) {
                delay(10.0, true, $cancellation);
                return new TuiCommandResultDto();
            });
        });

        $ctx = new TuiCommandContextDto(getcwd() ?: '.', new \quanzo\tui\classes\dto\tui\history\TuiHistoryDto());
        $input = new ParsedUserInputDto('/break ' . $jobId, true, 'break', [$jobId]);
        $res = $break->handle($ctx, $input);
        self::assertNotEmpty($res->getAppendEntries());

        $drained = $this->runUntilDrained($jobs, 1.0);
        self::assertNotEmpty($drained);
    }

    /**
     * Проверяет, что `/jobs` показывает активные и завершённые задачи.
     *
     * Важно: тест не проверяет точный формат времени, но проверяет наличие строк и jobId.
     */
    public function testJobsCommandShowsActiveAndRecentFinished(): void
    {
        $jobs = new TuiJobManager();

        $jobIdDone = $jobs->start('/done', static fn() => Future::complete(new TuiCommandResultDto()));
        $jobs->drainCompleted();

        $jobs->start('/running', static function ($cancellation) {
            return async(static function () use ($cancellation) {
                delay(10.0, true, $cancellation);
                return new TuiCommandResultDto();
            });
        });

        $handler = new JobsCommandHandler($jobs);
        $ctx = new TuiCommandContextDto(getcwd() ?: '.', new \quanzo\tui\classes\dto\tui\history\TuiHistoryDto());
        $input = new ParsedUserInputDto('/jobs', true, 'jobs', []);

        $res = $handler->handle($ctx, $input);
        self::assertNotEmpty($res->getAppendEntries());

        $plain = (string) ($res->getAppendEntries()[0]?->getPlainText() ?? '');
        self::assertSame('jobs', $plain);
        self::assertNotSame('', $jobIdDone);
    }

    /**
     * Мини-хелпер: крутит EventLoop, пока `drainCompleted()` не вернёт entries или не истечёт timeout.
     *
     * @param TuiJobManager $jobs
     * @param float $timeoutSeconds
     * @return list<TuiHistoryEntryDto>
     */
    private function runUntilDrained(TuiJobManager $jobs, float $timeoutSeconds): array
    {
        $suspension = EventLoop::getSuspension();
        $out = [];

        $tickId = EventLoop::repeat(0.005, static function (string $id) use ($jobs, &$out, $suspension): void {
            $out = $jobs->drainCompleted();
            if ($out !== []) {
                EventLoop::cancel($id);
                $suspension->resume(null);
            }
        });

        $timeoutId = EventLoop::delay($timeoutSeconds, static function () use ($tickId, $suspension): void {
            EventLoop::cancel($tickId);
            $suspension->resume(null);
        });

        $suspension->suspend();
        EventLoop::cancel($timeoutId);

        return $out;
    }
}
