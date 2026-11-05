<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\help;

use quanzo\tui\classes\dto\tui\help\TuiCommandHelpCatalogDto;
use quanzo\tui\interfaces\tui\command\TuiCommandRegistryInterface;

/**
 * Собирает строки справки из `getHelp()` зарегистрированных команд.
 *
 * Пустой `getHelp()` не добавляет строку. Порядок строк — порядок регистрации.
 *
 * Пример использования:
 *
 * ```php
 * $catalog = TuiCommandHelpCollector::fromRegistry($dispatcher);
 * ```
 */
final class TuiCommandHelpCollector
{
    /**
     * Собирает каталог. Без реестра каталог пустой.
     *
     * @param TuiCommandRegistryInterface|null $registry Реестр команд или null
     * @return TuiCommandHelpCatalogDto
     */
    public static function fromRegistry(?TuiCommandRegistryInterface $registry): TuiCommandHelpCatalogDto
    {
        if ($registry === null) {
            return new TuiCommandHelpCatalogDto([]);
        }

        $rows = [];
        foreach ($registry->listHandlers() as $handler) {
            foreach ($handler->getHelp()->getEntries() as $entry) {
                $rows[] = clone $entry;
            }
        }

        return new TuiCommandHelpCatalogDto($rows);
    }
}
