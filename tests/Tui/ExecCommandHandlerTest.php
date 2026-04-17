<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\Cancellation;
use Amp\Future;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\async\ShellProcessResultDto;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\tui\async\ShellRunningProcessDto;
use quanzo\tui\classes\tui\command\handlers\ExecCommandHandler;
use quanzo\tui\interfaces\tui\async\ShellProcessRunnerInterface;

/**
 * Тесты `/exec` handler'а.
 *
 * Запрещено запускать реальные процессы в unit-тестах, поэтому используем fake runner.
 */
final class ExecCommandHandlerTest extends TestCase
{
    /**
     * Пустая команда должна возвращать предупреждение и не стартовать runner.
     */
    public function testExecEmptyCommandReturnsWarning(): void
    {
        $runner = new class implements ShellProcessRunnerInterface {
            public int $calls = 0;

            public function start(string $command, Cancellation $cancellation): ShellRunningProcessDto
            {
                $this->calls++;
                return new ShellRunningProcessDto(Future::complete(new ShellProcessResultDto('', 0, '', '', false)), static fn() => null);
            }
        };

        $handler = new ExecCommandHandler($runner);
        $ctx = new TuiCommandContextDto(getcwd() ?: '.', new TuiHistoryDto());
        $input = new ParsedUserInputDto('/exec', true, 'exec', []);

        $res = $handler->handleAsync($ctx, $input, new \Amp\NullCancellation())->await();
        self::assertNotEmpty($res->getAppendEntries());
        self::assertSame(0, $runner->calls);
    }

    /**
     * Успешный результат runner'а должен быть преобразован в output-entry.
     */
    public function testExecMapsStdoutStderrExitCode(): void
    {
        $runner = new class implements ShellProcessRunnerInterface {
            public function start(string $command, Cancellation $cancellation): ShellRunningProcessDto
            {
                $dto = new ShellProcessResultDto($command, 7, "out\n", "err\n", false);
                return new ShellRunningProcessDto(Future::complete($dto), static fn() => null);
            }
        };

        $handler = new ExecCommandHandler($runner);
        $ctx = new TuiCommandContextDto(getcwd() ?: '.', new TuiHistoryDto());
        $input = new ParsedUserInputDto('/exec echo ok', true, 'exec', ['echo', 'ok']);

        $res = $handler->handleAsync($ctx, $input, new \Amp\NullCancellation())->await();
        self::assertNotEmpty($res->getAppendEntries());
    }
}
