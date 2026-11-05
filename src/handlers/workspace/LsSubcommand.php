<?php

declare(strict_types=1);

namespace quanzo\tui\handlers\workspace;

use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCatalogDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;
use quanzo\tui\classes\dto\tui\view\blocks\ListBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;

/**
 * Подкоманда `/ws ls`: ограниченный список файлов текущего каталога.
 *
 * Пример использования:
 *
 * ```php
 * $result = (new LsSubcommand())->execute($ctx, new WorkspaceSubcommandParametersDto([]), $catalog);
 * ```
 */
final class LsSubcommand extends AbstractWorkspaceSubcommand
{
    private const MAX_ROWS = 20;

    /**
     * Возвращает имя подкоманды.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ls';
    }

    /**
     * Возвращает описание для `/ws help`.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Содержимое каталога, не больше 20 записей. Лишние аргументы игнорируются. Нет каталога — пустая таблица';
    }

    /**
     * Показывает до 20 записей рабочего каталога.
     *
     * Точки `.` и `..` пропускаются. Несуществующий каталог даёт пустую таблицу.
     * Параметры принимаются контрактом и на состав списка не влияют.
     *
     * @param TuiCommandContextDto $ctx Контекст TUI: рабочий каталог и история
     * @param WorkspaceSubcommandParametersDto $parameters Токены после `ls`
     * @param WorkspaceSubcommandCatalogDto $catalog Зарегистрированные подкоманды
     * @return TuiCommandResultDto
     */
    public function execute(
        TuiCommandContextDto $ctx,
        WorkspaceSubcommandParametersDto $parameters,
        WorkspaceSubcommandCatalogDto $catalog,
    ): TuiCommandResultDto {
        $cwd = $ctx->getCwd();
        $rows = [];
        $items = @scandir($cwd) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $cwd . DIRECTORY_SEPARATOR . $name;
            $type = is_dir($path) ? 'dir' : 'file';
            $rows[] = [$name, $type];
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        $panel = new PanelBlockDto('Workspace', [
            new ListBlockDto(['Root: ' . $cwd]),
            new TableBlockDto(['Name', 'Type'], $rows),
        ]);

        return $this->output('ws ls', 'workspace listed', [$panel]);
    }
}
