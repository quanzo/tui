<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\KeyHintsBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\ListBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TextBlockDto;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/help`.
 *
 * Пример использования:
 *
 * ```php
 * (new HelpCommandHandler())->handle($ctx, $input);
 * ```
 */
final class HelpCommandHandler implements TuiCommandHandlerInterface
{
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
     * Формирует вывод справки по доступным командам.
     *
     * Результат возвращается как entry с блоками (panel/table/list/hints), чтобы рендер мог красиво
     * форматировать данные.
     *
     * В диагностических целях поддерживается `NEURON_TUI_HELP_ASCII=1` — упрощённый ASCII-вариант текста.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $asciiHelp = (string) (getenv('NEURON_TUI_HELP_ASCII') ?: '');
        $asciiMode = ($asciiHelp === '1' || strtolower($asciiHelp) === 'true');

        $rows = [
            ['/help', 'Справка по командам'],
            ['/ws ls', 'Показать рабочее пространство; /ws help — справка по подкомандам'],
            ['/long 10', 'Пример длительной async-команды (можно прервать через /break)'],
            ['/exec ls -la', 'Запустить shell-команду (результат появится после завершения)'],
            ['/break', 'Прервать последнюю активную async-задачу'],
            ['/break job_1', 'Прервать конкретную задачу по jobId'],
            ['/jobs', 'Показать список async-задач (active + recent finished)'],
            ['/clear', 'Очистить историю'],
            ['/exit', 'Выйти из TUI'],
        ];

        $listItems = [
            'Tab — переключить фокус (ввод/просмотр)',
            'Стрелки/PgUp/PgDn — прокрутка вывода (в режиме просмотра)',
            'Bracketed Paste — корректная многострочная вставка',
        ];
        $bullet = '•';
        $title = 'Neuron TUI';
        if ($asciiMode) {
            $bullet = '-';
            $title = 'Neuron TUI (ASCII help)';
            $listItems = [
                'Tab - switch focus (input/view)',
                'Arrows/PgUp/PgDn - scroll output (view mode)',
                'Bracketed Paste - multiline paste',
            ];
        }

        $panel = new PanelBlockDto($title, [
            new TextBlockDto('Команды:'),
            new TableBlockDto(['Command', 'Description'], $rows),
            (new ListBlockDto($listItems))->setBullet($bullet),
            new KeyHintsBlockDto(['Tab: focus', 'Enter: run', 'Ctrl+C: exit', '/help']),
        ]);

        $entry = TuiHistoryEntryDto::output('help')
            ->setBlocks([$panel])
            ->setPlainText('help');

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }
}
