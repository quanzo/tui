<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

/**
 * Реестр TUI-команд, из которого `/help` читает справку handlers.
 *
 * Пример использования:
 *
 * ```php
 * foreach ($registry->listHandlers() as $handler) {
 *     $handler->getHelp();
 * }
 * ```
 */
interface TuiCommandRegistryInterface
{
    /**
     * Возвращает handlers в порядке регистрации.
     *
     * Это те же экземпляры, что лежат в реестре. Новый только список.
     *
     * @return list<TuiNamedCommandHandlerInterface>
     */
    public function listHandlers(): array;
}
