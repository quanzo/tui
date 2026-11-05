<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;

/**
 * Базовый контракт handler'а TUI-команды: имя и собственная справка.
 *
 * Нужен, чтобы `TuiCommandDispatcher` мог хранить общий реестр команд, не привязываясь
 * к конкретному стилю исполнения (sync/async). Справку команды сообщает сам handler.
 *
 * Пример использования:
 *
 * ```php
 * final class ClearCommandHandler implements TuiCommandHandlerInterface
 * {
 *     public function getName(): string { return 'clear'; }
 *
 *     public function getHelp(): TuiCommandHelpDto
 *     {
 *         return new TuiCommandHelpDto([new TuiHelpRowDto('/clear', 'Очистить историю')]);
 *     }
 * }
 * ```
 */
interface TuiNamedCommandHandlerInterface
{
    /**
     * Возвращает имя команды (без префикса `/`).
     *
     * Пример: `help` для команды `/help`.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Возвращает справку команды для сборки `/help`.
     *
     * Пустой список строк означает, что команда в таблицу не попадает.
     * Описание живёт здесь, а не в общем тексте руководства.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto;
}
