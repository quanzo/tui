<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

/**
 * Handler, которому нужен живой реестр команд, чтобы собрать справку.
 *
 * Пример использования:
 *
 * ```php
 * $help->setCommandRegistry($dispatcher);
 * ```
 */
interface TuiCommandRegistryAwareInterface
{
    /**
     * Подключает реестр, из которого handler читает справку других команд.
     *
     * @param TuiCommandRegistryInterface $registry Диспетчер или другой реестр
     * @return self
     */
    public function setCommandRegistry(TuiCommandRegistryInterface $registry): self;
}
