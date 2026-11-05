<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCatalogDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;

/**
 * Контракт подкоманды `/ws`.
 *
 * Имя и описание живут в самой подкоманде. Аргументы после имени приходят уже разобранными токенами.
 *
 * Пример использования:
 *
 * ```php
 * final class LsSubcommand extends AbstractWorkspaceSubcommand
 * {
 *     public function getName(): string { return 'ls'; }
 *     public function getDescription(): string { return 'Показать каталог'; }
 *     public function execute(
 *         TuiCommandContextDto $ctx,
 *         WorkspaceSubcommandParametersDto $parameters,
 *         WorkspaceSubcommandCatalogDto $catalog,
 *     ): TuiCommandResultDto {
 *         return new TuiCommandResultDto();
 *     }
 * }
 * ```
 */
interface WorkspaceSubcommandInterface
{
    /**
     * Возвращает имя подкоманды без префикса `/ws`.
     *
     * Пример: `ls` для вызова `/ws ls`.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Возвращает описание подкоманды для справки `/ws help`.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Выполняет подкоманду.
     *
     * @param TuiCommandContextDto $ctx Контекст TUI: рабочий каталог и история
     * @param WorkspaceSubcommandParametersDto $parameters Токены после имени подкоманды
     * @param WorkspaceSubcommandCatalogDto $catalog Зарегистрированные подкоманды
     * @return TuiCommandResultDto
     */
    public function execute(
        TuiCommandContextDto $ctx,
        WorkspaceSubcommandParametersDto $parameters,
        WorkspaceSubcommandCatalogDto $catalog,
    ): TuiCommandResultDto;
}
