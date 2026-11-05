<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\Cancellation;
use Amp\Future;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\async\ShellProcessResultDto;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\tui\async\ShellRunningProcessDto;
use quanzo\tui\handlers\ExecCommandHandler;
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

    /**
     * Аргументы склеиваются пробелом без повторного экранирования.
     *
     * Кавычки парсера уже сняты: `my file` снова станет двумя словами для bash.
     * Токен `&&` остаётся оператором, потому что внутри него нет пробела.
     *
     * @param list<string> $args
     * @param string $expected
     */
    #[DataProvider('joinedCommandCases')]
    public function testExecJoinsArgsWithoutRequoting(array $args, string $expected): void
    {
        $runner = new class implements ShellProcessRunnerInterface {
            public string $seen = '';

            public function start(string $command, Cancellation $cancellation): ShellRunningProcessDto
            {
                $this->seen = $command;
                $dto = new ShellProcessResultDto($command, 0, '', '', false);
                return new ShellRunningProcessDto(Future::complete($dto), static fn () => null);
            }
        };

        $handler = new ExecCommandHandler($runner);
        $ctx = new TuiCommandContextDto(getcwd() ?: '.', new TuiHistoryDto());
        $input = new ParsedUserInputDto('/exec', true, 'exec', $args);
        $handler->handleAsync($ctx, $input, new \Amp\NullCancellation())->await();

        self::assertSame($expected, $runner->seen);
    }

    /**
     * @return list<array{0:list<string>,1:string}>
     */
    public static function joinedCommandCases(): array
    {
        return [
            [['ls', 'my file'], 'ls my file'],
            [['sleep', '3', '&&', 'echo', 'done'], 'sleep 3 && echo done'],
            [['echo', 'ok'], 'echo ok'],
            [['php', '-v'], 'php -v'],
            [['ls', '-la'], 'ls -la'],
            [['echo', 'a"b'], 'echo a"b'],
            [['cmd'], 'cmd'],
            [['a', 'b', 'c'], 'a b c'],
            [["a\nb"], "a\nb"],
            [['0'], '0'],
        ];
    }
}
