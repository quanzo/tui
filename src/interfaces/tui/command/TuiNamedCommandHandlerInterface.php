<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

/**
 * Базовый контракт handler'а TUI-команды: только имя.
 *
 * Нужен, чтобы `TuiCommandDispatcher` мог хранить общий реестр команд, не привязываясь
 * к конкретному стилю исполнения (sync/async).
 *
 * Пример использования:
 *
 * ```php
 * final class HelpCommandHandler implements TuiCommandHandlerInterface
 * {
 *     public function getName(): string { return 'help'; }
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
}
