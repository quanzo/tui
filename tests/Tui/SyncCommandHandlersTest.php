<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\DeferredFuture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\handlers\BreakCommandHandler;
use quanzo\tui\handlers\ClearCommandHandler;
use quanzo\tui\handlers\ExitCommandHandler;
use quanzo\tui\handlers\HelpCommandHandler;
use quanzo\tui\handlers\JobsCommandHandler;
use quanzo\tui\handlers\LongCommandHandler;
use quanzo\tui\handlers\WorkspaceCommandHandler;

/**
 * Тесты sync-handlers: help, clear, exit, ws, break, jobs и разбор секунд `/long`.
 */
final class SyncCommandHandlersTest extends TestCase
{
    /**
     * Режим ASCII-справки включается только для `1` и `true`.
     *
     * @param string $env
     * @param string $title
     */
    #[DataProvider('helpAsciiCases')]
    public function testHelpAsciiMode(string $env, string $title): void
    {
        $previous = getenv('NEURON_TUI_HELP_ASCII');
        putenv('NEURON_TUI_HELP_ASCII=' . $env);

        try {
            $result = (new HelpCommandHandler())->handle($this->context('.'), $this->input('/help', 'help', []));
            $panel = $result->getAppendEntries()[0]->getBlocks()[0];
            $this->assertInstanceOf(PanelBlockDto::class, $panel);
            $this->assertSame($title, $panel->getTitle());
            $this->assertSame('help', $result->getAppendEntries()[0]->getPlainText());
        } finally {
            if ($previous === false) {
                putenv('NEURON_TUI_HELP_ASCII');
            } else {
                putenv('NEURON_TUI_HELP_ASCII=' . $previous);
            }
        }
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    public static function helpAsciiCases(): array
    {
        return [
            ['1', 'Neuron TUI (ASCII help)'],
            ['true', 'Neuron TUI (ASCII help)'],
            ['TRUE', 'Neuron TUI (ASCII help)'],
            ['0', 'Neuron TUI'],
            ['', 'Neuron TUI'],
            ['false', 'Neuron TUI'],
            ['yes', 'Neuron TUI'],
            ['2', 'Neuron TUI'],
            [' ', 'Neuron TUI'],
            ['no', 'Neuron TUI'],
        ];
    }

    /**
     * `/clear` и `/exit` выставляют флаги и не путают их местами.
     */
    public function testClearAndExitFlags(): void
    {
        $ctx = $this->context('.');
        $clear = (new ClearCommandHandler())->handle($ctx, $this->input('/clear', 'clear', []));
        $this->assertTrue($clear->isClearHistory());
        $this->assertFalse($clear->isExit());
        $this->assertSame('История очищена', $clear->getAppendEntries()[0]->getPlainText());

        $exit = (new ExitCommandHandler())->handle($ctx, $this->input('/exit', 'exit', ['now']));
        $this->assertTrue($exit->isExit());
        $this->assertFalse($exit->isClearHistory());
        $this->assertSame('Выход...', $exit->getAppendEntries()[0]->getPlainText());
    }

    /**
     * `/ws` без аргумента и с `ls` читает каталог. Чужая подкоманда и битый путь не падают.
     */
    public function testWorkspaceListsDirectoryAndRejectsBadInput(): void
    {
        $dir = sys_get_temp_dir() . '/tui-ws-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/a.txt', 'x');
        mkdir($dir . '/sub');

        try {
            $handler = new WorkspaceCommandHandler();
            $listed = $handler->handle($this->context($dir), $this->input('/ws', 'ws', []));
            $names = $this->tableNames($listed->getAppendEntries()[0]->getBlocks()[0]);
            $this->assertContains('a.txt', $names);
            $this->assertContains('sub', $names);

            $explicit = $handler->handle($this->context($dir), $this->input('/ws ls', 'ws', ['ls']));
            $this->assertSame($names, $this->tableNames($explicit->getAppendEntries()[0]->getBlocks()[0]));

            $unknown = $handler->handle($this->context($dir), $this->input('/ws nope', 'ws', ['nope']));
            $this->assertSame('Неизвестная подкоманда: nope', $unknown->getAppendEntries()[0]->getPlainText());

            $missing = $handler->handle(
                $this->context('/no/such/tui-dir'),
                $this->input('/ws ls', 'ws', ['ls']),
            );
            $this->assertSame([], $this->tableRows($missing->getAppendEntries()[0]->getBlocks()[0]));
        } finally {
            @unlink($dir . '/a.txt');
            @rmdir($dir . '/sub');
            @rmdir($dir);
        }
    }

    /**
     * `/break` без задач, с пустым id и с чужим id при живой задаче.
     */
    public function testBreakWithoutJobsAndUnknownId(): void
    {
        $idle = new BreakCommandHandler(new TuiJobManager());
        $ctx = $this->context('.');

        $none = $idle->handle($ctx, $this->input('/break', 'break', []));
        $this->assertSame('no active jobs', $none->getAppendEntries()[0]->getPlainText());

        $blank = $idle->handle($ctx, $this->input('/break   ', 'break', ['   ']));
        $this->assertSame('no active jobs', $blank->getAppendEntries()[0]->getPlainText());

        $jobs = new TuiJobManager();
        $jobs->start('/x', static fn () => (new DeferredFuture())->getFuture());
        $handler = new BreakCommandHandler($jobs);

        $missing = $handler->handle($ctx, $this->input('/break missing', 'break', ['missing']));
        $this->assertSame('job not found', $missing->getAppendEntries()[0]->getPlainText());
        $this->assertCount(1, $jobs->listActiveJobIds());

        $last = $handler->handle($ctx, $this->input('/break', 'break', []));
        $this->assertSame('break requested', $last->getAppendEntries()[0]->getPlainText());
    }

    /**
     * `/jobs` на пустом менеджере печатает таблицу без строк.
     */
    public function testJobsOnEmptyManager(): void
    {
        $result = (new JobsCommandHandler(new TuiJobManager()))->handle(
            $this->context('.'),
            $this->input('/jobs', 'jobs', []),
        );
        $this->assertSame('jobs', $result->getAppendEntries()[0]->getPlainText());
        $this->assertSame([], $this->tableRows($result->getAppendEntries()[0]->getBlocks()[0]));
    }

    /**
     * Нечисловые и пограничные секунды `/long` приводятся к 1..600, мусор становится 5.
     *
     * @param string $raw
     * @param int $expected
     */
    #[DataProvider('longSecondsCases')]
    public function testLongParseSeconds(string $raw, int $expected): void
    {
        $method = new \ReflectionMethod(LongCommandHandler::class, 'parseSeconds');
        $input = $this->input('/long', 'long', $raw === '' ? [] : [$raw]);
        $actual = $method->invoke(new LongCommandHandler(), $input);
        $this->assertSame($expected, $actual);
    }

    /**
     * @return list<array{0:string,1:int}>
     */
    public static function longSecondsCases(): array
    {
        return [
            ['', 5],
            ['0', 5],
            ['-1', 5],
            ['abc', 5],
            ['1', 1],
            ['10abc', 10],
            ['1.9', 1],
            ['600', 600],
            ['601', 600],
            ['  3  ', 3],
            ['00', 5],
            ['999999', 600],
        ];
    }

    /**
     * @param string $cwd
     */
    private function context(string $cwd): TuiCommandContextDto
    {
        return new TuiCommandContextDto($cwd, new TuiHistoryDto());
    }

    /**
     * @param string $raw
     * @param string $name
     * @param list<string> $args
     */
    private function input(string $raw, string $name, array $args): ParsedUserInputDto
    {
        return new ParsedUserInputDto($raw, true, $name, $args);
    }

    /**
     * @param mixed $block
     * @return list<string>
     */
    private function tableNames(mixed $block): array
    {
        $names = [];
        foreach ($this->tableRows($block) as $row) {
            $names[] = (string) ($row[0] ?? '');
        }
        return $names;
    }

    /**
     * @param mixed $block
     * @return list<list<string>>
     */
    private function tableRows(mixed $block): array
    {
        $this->assertInstanceOf(PanelBlockDto::class, $block);
        $table = $block->getBody()[1] ?? $block->getBody()[0];
        $this->assertInstanceOf(TableBlockDto::class, $table);
        return $table->getRows();
    }
}
