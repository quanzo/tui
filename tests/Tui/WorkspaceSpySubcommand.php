<?php

declare(strict_types=1);

namespace Tests\Tui;

use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCatalogDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;
use quanzo\tui\handlers\workspace\AbstractWorkspaceSubcommand;

/**
 * Подкоманда-шпион: кладёт полученные токены в плоский текст результата.
 *
 * Нужна, чтобы проверить разбор хвоста `/ws` отдельно от `ls` и `help`.
 *
 * Пример использования:
 *
 * ```php
 * $spy = new WorkspaceSpySubcommand('ls');
 * ```
 */
final class WorkspaceSpySubcommand extends AbstractWorkspaceSubcommand
{
    /**
     * @param string $name Имя, под которым шпион регистрируется
     */
    public function __construct(private readonly string $name)
    {
    }

    /**
     * Возвращает имя, заданное в конструкторе.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает описание, по которому видно, что это шпион.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'spy ' . $this->name;
    }

    /**
     * Кодирует токены в JSON и возвращает их как плоский текст.
     *
     * @param TuiCommandContextDto $ctx Контекст TUI, шпионом не используется
     * @param WorkspaceSubcommandParametersDto $parameters Токены после имени подкоманды
     * @param WorkspaceSubcommandCatalogDto $catalog Каталог, шпионом не используется
     * @return TuiCommandResultDto
     */
    public function execute(
        TuiCommandContextDto $ctx,
        WorkspaceSubcommandParametersDto $parameters,
        WorkspaceSubcommandCatalogDto $catalog,
    ): TuiCommandResultDto {
        $encoded = json_encode($parameters->getTokens(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->output('spy', $encoded === false ? 'encode-failed' : $encoded, []);
    }
}
