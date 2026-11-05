<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpSectionDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpTableDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\HeadingBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\KeyHintsBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\ListBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TextBlockDto;
use quanzo\tui\classes\tui\help\TuiCommandHelpCollector;
use quanzo\tui\classes\tui\help\TuiUserGuide;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandRegistryAwareInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandRegistryInterface;
use quanzo\tui\interfaces\tui\view\TuiBlockInterface;

/**
 * Handler команды `/help`.
 *
 * Печатает пользовательское руководство: экран, режимы и клавиши из {@see TuiUserGuide},
 * строки команд — из `getHelp()` handlers реестра. Тот же текст доступен до запуска TUI
 * через `./bin/console help interactive`.
 *
 * Пример использования:
 *
 * ```php
 * $help = (new HelpCommandHandler())->setCommandRegistry($dispatcher);
 * $help->handle($ctx, $input);
 * ```
 */
final class HelpCommandHandler implements TuiCommandHandlerInterface, TuiCommandRegistryAwareInterface
{
    private ?TuiCommandRegistryInterface $registry;

    /**
     * @param TuiCommandRegistryInterface|null $registry Реестр команд. Без него раздел «Команды» пуст
     */
    public function __construct(?TuiCommandRegistryInterface $registry = null)
    {
        $this->registry = $registry;
    }

    /**
     * Подключает реестр, из которого собирается таблица команд.
     *
     * @param TuiCommandRegistryInterface $registry Диспетчер команд
     * @return self
     */
    public function setCommandRegistry(TuiCommandRegistryInterface $registry): self
    {
        $this->registry = $registry;
        return $this;
    }

    /**
     * Возвращает имя команды (без префикса `/`).
     *
     * @return string
     */
    public function getName(): string
    {
        return 'help';
    }

    /**
     * Возвращает справку самой команды `/help`.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto
    {
        return new TuiCommandHelpDto([
            new TuiHelpRowDto('/help', 'Это руководство: экран, режимы, клавиши и команды'),
        ]);
    }

    /**
     * Формирует панель руководства.
     *
     * `NEURON_TUI_HELP_ASCII=1` или `true` меняет заголовок на `Neuron TUI (ASCII help)`
     * и маркер списка на `-`. Текст секций остаётся русским.
     *
     * @param TuiCommandContextDto $ctx Контекст TUI
     * @param ParsedUserInputDto $input Разобранный ввод `/help`
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $guide = TuiUserGuide::build(TuiCommandHelpCollector::fromRegistry($this->registry));
        $asciiMode = $this->isAsciiMode();
        $title = $asciiMode ? 'Neuron TUI (ASCII help)' : $guide->getTitle();
        $bullet = $asciiMode ? '-' : '•';

        $body = [new TextBlockDto($guide->getIntro())];
        foreach ($guide->getSections() as $section) {
            foreach ($this->sectionBlocks($section, $bullet) as $block) {
                $body[] = $block;
            }
        }
        $body[] = new KeyHintsBlockDto($guide->getKeyHints());

        $entry = TuiHistoryEntryDto::output('help')
            ->setBlocks([new PanelBlockDto($title, $body)])
            ->setPlainText('help');

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }

    /**
     * Проверяет, запрошен ли ASCII-вариант панели.
     *
     * @return bool
     */
    private function isAsciiMode(): bool
    {
        $asciiHelp = (string) (getenv('NEURON_TUI_HELP_ASCII') ?: '');

        return $asciiHelp === '1' || strtolower($asciiHelp) === 'true';
    }

    /**
     * Раскладывает секцию руководства на блоки панели.
     *
     * @param TuiHelpSectionDto $section Секция руководства
     * @param string $bullet Маркер списка: `•` или `-`
     * @return list<TuiBlockInterface>
     */
    private function sectionBlocks(TuiHelpSectionDto $section, string $bullet): array
    {
        $blocks = [(new HeadingBlockDto($section->getTitle()))->setLevel(2)];
        foreach ($section->getParagraphs() as $paragraph) {
            $blocks[] = new TextBlockDto($paragraph);
        }
        if ($section->getItems() !== []) {
            $blocks[] = (new ListBlockDto($section->getItems()))->setBullet($bullet);
        }
        $table = $section->getTable();
        if ($table !== null) {
            $blocks[] = $this->tableBlock($table);
        }

        return $blocks;
    }

    /**
     * Превращает таблицу руководства в блок вывода.
     *
     * @param TuiHelpTableDto $table Таблица секции
     * @return TableBlockDto
     */
    private function tableBlock(TuiHelpTableDto $table): TableBlockDto
    {
        $headers = [$table->getLabelHeader()];
        if ($table->hasScopeColumn()) {
            $headers[] = $table->getScopeHeader();
        }
        $headers[] = $table->getDescriptionHeader();

        $rows = [];
        foreach ($table->getRows() as $row) {
            $cells = [$row->getLabel()];
            if ($table->hasScopeColumn()) {
                $cells[] = $row->getScope();
            }
            $cells[] = $row->getDescription();
            $rows[] = $cells;
        }

        return new TableBlockDto($headers, $rows);
    }
}
