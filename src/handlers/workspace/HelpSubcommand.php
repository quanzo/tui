<?php

declare(strict_types=1);

namespace quanzo\tui\handlers\workspace;

use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCatalogDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;

/**
 * Подкоманда `/ws help`: справка по зарегистрированным подкомандам workspace.
 *
 * Пример использования:
 *
 * ```php
 * $result = (new HelpSubcommand())->execute($ctx, new WorkspaceSubcommandParametersDto([]), $catalog);
 * ```
 */
final class HelpSubcommand extends AbstractWorkspaceSubcommand
{
    /**
     * Возвращает имя подкоманды.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'help';
    }

    /**
     * Возвращает описание для таблицы справки.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Справка по подкомандам workspace';
    }

    /**
     * Печатает таблицу подкоманд из каталога.
     *
     * Параметры после `help` не меняют таблицу: справка всегда полная.
     *
     * @param TuiCommandContextDto $ctx Контекст TUI
     * @param WorkspaceSubcommandParametersDto $parameters Токены после `help`
     * @param WorkspaceSubcommandCatalogDto $catalog Зарегистрированные подкоманды
     * @return TuiCommandResultDto
     */
    public function execute(
        TuiCommandContextDto $ctx,
        WorkspaceSubcommandParametersDto $parameters,
        WorkspaceSubcommandCatalogDto $catalog,
    ): TuiCommandResultDto {
        $rows = [];
        foreach ($catalog->getItems() as $item) {
            $rows[] = [$item->getUsage(), $item->getDescription()];
        }

        $panel = new PanelBlockDto('Workspace', [
            new TableBlockDto(['Command', 'Description'], $rows),
        ]);

        return $this->output('ws help', 'workspace help', [$panel]);
    }
}
