<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\DeferredFuture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpCatalogDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpGuideDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\dto\tui\view\blocks\HeadingBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\ListBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TextBlockDto;
use quanzo\tui\classes\tui\async\ShellProcessRunner;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\classes\tui\command\TuiCommandDispatcher;
use quanzo\tui\classes\tui\help\TuiCommandHelpCollector;
use quanzo\tui\classes\tui\help\TuiUserGuide;
use quanzo\tui\command\InteractiveCommand;
use quanzo\tui\handlers\BreakCommandHandler;
use quanzo\tui\handlers\ClearCommandHandler;
use quanzo\tui\handlers\ExecCommandHandler;
use quanzo\tui\handlers\ExitCommandHandler;
use quanzo\tui\handlers\HelpCommandHandler;
use quanzo\tui\handlers\JobsCommandHandler;
use quanzo\tui\handlers\LongCommandHandler;
use quanzo\tui\handlers\WorkspaceCommandHandler;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

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
     * Панель `/help` содержит секции руководства. ASCII-режим меняет только маркер списка.
     */
    public function testHelpGuideSections(): void
    {
        $unicode = $this->helpPanel('0');
        $ascii = $this->helpPanel('1');

        $titles = ['Экран', 'Режимы', 'Клавиши', 'Команды', 'Фоновые задачи'];
        $this->assertSame($titles, $this->headingTexts($unicode));
        $this->assertSame($titles, $this->headingTexts($ascii));
        $this->assertSame('•', $this->singleList($unicode)->getBullet());
        $this->assertSame('-', $this->singleList($ascii)->getBullet());
        $this->assertStringContainsString('ПРОСМОТР', $this->joinedText($unicode));
        $this->assertStringContainsString('ПРОСМОТР', $this->joinedText($ascii));
    }

    /**
     * Клавиши ищутся в секции руководства, команды — в строках `getHelp()`.
     * Выдуманные сочетания и команды отсутствуют.
     *
     * @param string $kind `key` или `command`
     * @param string $label Фрагмент первой колонки
     * @param bool $present true, если фрагмент должен найтись
     */
    #[DataProvider('helpEntryCases')]
    public function testHelpEntryPresence(string $kind, string $label, bool $present): void
    {
        $labels = $kind === 'key'
            ? $this->sectionLabels(TuiUserGuide::build(new TuiCommandHelpCatalogDto([])), 'Клавиши')
            : $this->rowLabels(TuiCommandHelpCollector::fromRegistry($this->productionDispatcher())->getRows());

        $found = false;
        foreach ($labels as $rowLabel) {
            if (str_contains($rowLabel, $label)) {
                $found = true;
                break;
            }
        }

        $this->assertSame($present, $found, $label);
    }

    /**
     * Набор известных и заведомо ложных подписей справки.
     *
     * @return list<array{0:string,1:string,2:bool}>
     */
    public static function helpEntryCases(): array
    {
        return [
            ['key', 'Tab', true],
            ['key', 'Ctrl+J', true],
            ['key', 'F2', true],
            ['key', 'PgUp', true],
            ['key', 'Enter', true],
            ['key', 'Ctrl+C', true],
            ['key', 'Home', true],
            ['key', 'Ctrl+Z', false],
            ['key', 'Shift+Tab', false],
            ['command', '/help', true],
            ['command', '/ws ls', true],
            ['command', '/long', true],
            ['command', '/exec', true],
            ['command', '/break', true],
            ['command', '/jobs', true],
            ['command', '/rm', false],
            ['command', 'Ctrl+Z', false],
            ['command', 'Shift+Tab', false],
        ];
    }

    /**
     * Без реестра раздел «Команды» пуст, остальные секции остаются.
     */
    public function testHelpWithoutRegistryHasEmptyCommandTable(): void
    {
        $panel = $this->helpPanel('0');

        $this->assertSame(
            ['Экран', 'Режимы', 'Клавиши', 'Команды', 'Фоновые задачи'],
            $this->headingTexts($panel),
        );
        $this->assertSame([], $this->commandTableRows($panel));
    }

    /**
     * Пустой `getHelp()` не добавляет строку в каталог.
     */
    public function testEmptyCommandHelpIsSkipped(): void
    {
        $dispatcher = new TuiCommandDispatcher([]);
        $dispatcher->addHandler(new ClearCommandHandler());
        $dispatcher->addHandler($this->namedHelp('silent', []));

        $labels = $this->rowLabels(TuiCommandHelpCollector::fromRegistry($dispatcher)->getRows());

        $this->assertSame(['/clear'], $labels);
    }

    /**
     * Порядок строк справки совпадает с порядком `addHandler`.
     */
    public function testCommandHelpFollowsRegistrationOrder(): void
    {
        $dispatcher = new TuiCommandDispatcher([]);
        $dispatcher->addHandler(new HelpCommandHandler());
        $dispatcher->addHandler(new ClearCommandHandler());
        $dispatcher->addHandler(new ExitCommandHandler());

        $labels = $this->rowLabels(TuiCommandHelpCollector::fromRegistry($dispatcher)->getRows());

        $this->assertSame(['/help', '/clear', '/exit'], $labels);
    }

    /**
     * Повторная регистрация того же имени заменяет текст и сохраняет позицию.
     */
    public function testRepeatedRegistrationReplacesHelpText(): void
    {
        $dispatcher = new TuiCommandDispatcher([]);
        $dispatcher->addHandler(new HelpCommandHandler());
        $dispatcher->addHandler($this->namedHelp('demo', [new TuiHelpRowDto('/demo', 'старое')]));
        $dispatcher->addHandler(new ExitCommandHandler());
        $dispatcher->addHandler($this->namedHelp('demo', [new TuiHelpRowDto('/demo', 'новое')]));

        $rows = TuiCommandHelpCollector::fromRegistry($dispatcher)->getRows();

        $this->assertSame(['/help', '/demo', '/exit'], $this->rowLabels($rows));
        $this->assertSame('новое', $rows[1]->getDescription());
    }

    /**
     * Плоский текст Symfony совпадает со сборкой того же реестра и не зависит от ASCII-флага.
     */
    public function testPlainHelpIgnoresAsciiFlag(): void
    {
        $previous = getenv('NEURON_TUI_HELP_ASCII');
        $command = $this->interactiveWithHandlers();
        $dispatcher = $this->dispatcherOf($command);
        $expected = TuiUserGuide::toPlainText(TuiUserGuide::build(
            TuiCommandHelpCollector::fromRegistry($dispatcher),
        ));

        putenv('NEURON_TUI_HELP_ASCII');

        try {
            $without = $command->getHelp();
            putenv('NEURON_TUI_HELP_ASCII=1');
            $with = $command->getHelp();

            $this->assertNotSame('', trim($with));
            $this->assertSame($without, $with);
            $this->assertSame($expected, $with);
            foreach (['Экран', 'Режимы', 'Клавиши', 'Команды', 'Фоновые задачи'] as $title) {
                $this->assertStringContainsString("\n" . $title . "\n", $with);
            }
        } finally {
            if ($previous === false) {
                putenv('NEURON_TUI_HELP_ASCII');
            } else {
                putenv('NEURON_TUI_HELP_ASCII=' . $previous);
            }
        }
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

            $withJunk = $handler->handle($this->context($dir), $this->input('/ws ls nope', 'ws', ['ls', 'nope']));
            $this->assertSame($names, $this->tableNames($withJunk->getAppendEntries()[0]->getBlocks()[0]));

            $unknown = $handler->handle($this->context($dir), $this->input('/ws nope', 'ws', ['nope']));
            $this->assertSame('Неизвестная подкоманда: nope', $unknown->getAppendEntries()[0]->getPlainText());

            $missing = $handler->handle(
                $this->context('/no/such/tui-dir'),
                $this->input('/ws ls', 'ws', ['ls']),
            );
            $this->assertSame([], $this->tableRows($missing->getAppendEntries()[0]->getBlocks()[0]));

            $help = $handler->handle($this->context($dir), $this->input('/ws help', 'ws', ['help']));
            $this->assertSame('workspace help', $help->getAppendEntries()[0]->getPlainText());
            $helpPanel = $help->getAppendEntries()[0]->getBlocks()[0];
            $this->assertInstanceOf(PanelBlockDto::class, $helpPanel);
            $helpTable = $helpPanel->getBody()[0];
            $this->assertInstanceOf(TableBlockDto::class, $helpTable);
            $this->assertSame(
                [
                    ['/ws help', 'Справка по подкомандам workspace'],
                    ['/ws ls', 'Содержимое каталога, не больше 20 записей. Лишние аргументы игнорируются. Нет каталога — пустая таблица'],
                ],
                $helpTable->getRows(),
            );
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

    /**
     * Строит панель `/help` при заданном значении `NEURON_TUI_HELP_ASCII` и восстанавливает окружение.
     *
     * @param string $env Значение переменной окружения
     * @return PanelBlockDto
     */
    private function helpPanel(string $env): PanelBlockDto
    {
        $previous = getenv('NEURON_TUI_HELP_ASCII');
        putenv('NEURON_TUI_HELP_ASCII=' . $env);

        try {
            $result = (new HelpCommandHandler())->handle($this->context('.'), $this->input('/help', 'help', []));
            $panel = $result->getAppendEntries()[0]->getBlocks()[0];
            $this->assertInstanceOf(PanelBlockDto::class, $panel);

            return $panel;
        } finally {
            if ($previous === false) {
                putenv('NEURON_TUI_HELP_ASCII');
            } else {
                putenv('NEURON_TUI_HELP_ASCII=' . $previous);
            }
        }
    }

    /**
     * Собирает заголовки секций внутри панели.
     *
     * @param PanelBlockDto $panel Панель `/help`
     * @return list<string>
     */
    private function headingTexts(PanelBlockDto $panel): array
    {
        $titles = [];
        foreach ($panel->getBody() as $block) {
            if ($block instanceof HeadingBlockDto) {
                $titles[] = $block->getText();
            }
        }

        return $titles;
    }

    /**
     * Возвращает единственный список панели.
     *
     * @param PanelBlockDto $panel Панель `/help`
     * @return ListBlockDto
     */
    private function singleList(PanelBlockDto $panel): ListBlockDto
    {
        $lists = [];
        foreach ($panel->getBody() as $block) {
            if ($block instanceof ListBlockDto) {
                $lists[] = $block;
            }
        }

        $this->assertCount(1, $lists);

        return $lists[0];
    }

    /**
     * Склеивает вступление и абзацы панели, чтобы проверить русский текст.
     *
     * @param PanelBlockDto $panel Панель `/help`
     * @return string
     */
    private function joinedText(PanelBlockDto $panel): string
    {
        $parts = [];
        foreach ($panel->getBody() as $block) {
            if ($block instanceof TextBlockDto) {
                $parts[] = $block->getText();
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Собирает подписи строк одной секции руководства.
     *
     * @param TuiHelpGuideDto $guide Собранное руководство
     * @param string $title Заголовок секции
     * @return list<string>
     */
    private function sectionLabels(TuiHelpGuideDto $guide, string $title): array
    {
        foreach ($guide->getSections() as $section) {
            if ($section->getTitle() !== $title) {
                continue;
            }
            $table = $section->getTable();
            $this->assertNotNull($table);

            return $this->rowLabels($table->getRows());
        }

        $this->fail('Секция не найдена: ' . $title);
    }

    /**
     * Собирает подписи строк справки.
     *
     * @param list<TuiHelpRowDto> $rows Строки каталога или таблицы
     * @return list<string>
     */
    private function rowLabels(array $rows): array
    {
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = $row->getLabel();
        }

        return $labels;
    }

    /**
     * Возвращает строки таблицы «Команды» внутри панели `/help`.
     *
     * @param PanelBlockDto $panel Панель `/help`
     * @return list<list<string>>
     */
    private function commandTableRows(PanelBlockDto $panel): array
    {
        $seen = false;
        foreach ($panel->getBody() as $block) {
            if ($block instanceof HeadingBlockDto && $block->getText() === 'Команды') {
                $seen = true;
                continue;
            }
            if ($seen && $block instanceof TableBlockDto) {
                return $block->getRows();
            }
        }

        $this->fail('Таблица команд не найдена');
    }

    /**
     * Реестр боевых handlers в том порядке, в каком их подключает `bin/console.php`.
     *
     * @return TuiCommandDispatcher
     */
    private function productionDispatcher(): TuiCommandDispatcher
    {
        $jobs = new TuiJobManager();
        $dispatcher = new TuiCommandDispatcher([]);
        $handlers = [
            new HelpCommandHandler($dispatcher),
            new WorkspaceCommandHandler(),
            new ClearCommandHandler(),
            new BreakCommandHandler($jobs),
            new JobsCommandHandler($jobs),
            new LongCommandHandler(),
            new ExecCommandHandler(new ShellProcessRunner()),
            new ExitCommandHandler(),
        ];
        foreach ($handlers as $handler) {
            $dispatcher->addHandler($handler);
        }

        return $dispatcher;
    }

    /**
     * Команда `interactive` с тем же набором handlers, что и точка входа.
     *
     * @return InteractiveCommand
     */
    private function interactiveWithHandlers(): InteractiveCommand
    {
        $jobs = new TuiJobManager();

        return (new InteractiveCommand())
            ->addHandler(new HelpCommandHandler())
            ->addHandler(new WorkspaceCommandHandler())
            ->addHandler(new ClearCommandHandler())
            ->addHandler(new BreakCommandHandler($jobs))
            ->addHandler(new JobsCommandHandler($jobs))
            ->addHandler(new LongCommandHandler())
            ->addHandler(new ExecCommandHandler(new ShellProcessRunner()))
            ->addHandler(new ExitCommandHandler());
    }

    /**
     * Достаёт диспетчер команды через reflection: публичного getter нет.
     *
     * @param InteractiveCommand $command Команда с уже добавленными handlers
     * @return TuiCommandDispatcher
     */
    private function dispatcherOf(InteractiveCommand $command): TuiCommandDispatcher
    {
        $method = new \ReflectionMethod($command, 'getDispatcher');
        $dispatcher = $method->invoke($command);
        $this->assertInstanceOf(TuiCommandDispatcher::class, $dispatcher);

        return $dispatcher;
    }

    /**
     * Stub-команда с заданными строками справки. Пустой список в таблицу не попадает.
     *
     * @param string $name Имя команды без `/`
     * @param list<TuiHelpRowDto> $entries Строки `getHelp()`
     * @return TuiCommandHandlerInterface
     */
    private function namedHelp(string $name, array $entries): TuiCommandHandlerInterface
    {
        return new class ($name, $entries) implements TuiCommandHandlerInterface {
            /**
             * @param string $name
             * @param list<TuiHelpRowDto> $entries
             */
            public function __construct(
                private readonly string $name,
                private readonly array $entries,
            ) {
            }

            /**
             * Возвращает имя stub-команды.
             *
             * @return string
             */
            public function getName(): string
            {
                return $this->name;
            }

            /**
             * Возвращает переданные строки справки.
             *
             * @return TuiCommandHelpDto
             */
            public function getHelp(): TuiCommandHelpDto
            {
                return new TuiCommandHelpDto($this->entries);
            }

            /**
             * Stub не выполняет команду.
             *
             * @param TuiCommandContextDto $ctx
             * @param ParsedUserInputDto $input
             * @return TuiCommandResultDto
             */
            public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
            {
                return new TuiCommandResultDto();
            }
        };
    }
}
