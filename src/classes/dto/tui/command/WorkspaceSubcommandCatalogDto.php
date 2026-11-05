<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\command;

/**
 * Каталог зарегистрированных подкоманд `/ws`.
 *
 * Хранит дескрипторы, а не живые обработчики: справке достаточно имени, описания и строки вызова.
 *
 * Пример использования:
 *
 * ```php
 * $catalog = new WorkspaceSubcommandCatalogDto([$descriptor]);
 * foreach ($catalog->getItems() as $item) {
 *     echo $item->getUsage();
 * }
 * ```
 */
final class WorkspaceSubcommandCatalogDto
{
    /** @var list<WorkspaceSubcommandDescriptorDto> */
    private array $items;

    /**
     * @param list<WorkspaceSubcommandDescriptorDto> $items Дескрипторы в порядке показа справки
     */
    public function __construct(array $items)
    {
        $copy = [];
        foreach (array_values($items) as $item) {
            $copy[] = new WorkspaceSubcommandDescriptorDto(
                $item->getName(),
                $item->getDescription(),
                $item->getUsage(),
            );
        }
        $this->items = $copy;
    }

    /**
     * Возвращает дескрипторы каталога.
     *
     * Каждый элемент — новый объект, чтобы вызывающий код не делил состояние с каталогом.
     *
     * @return list<WorkspaceSubcommandDescriptorDto>
     */
    public function getItems(): array
    {
        $copy = [];
        foreach ($this->items as $item) {
            $copy[] = new WorkspaceSubcommandDescriptorDto(
                $item->getName(),
                $item->getDescription(),
                $item->getUsage(),
            );
        }

        return $copy;
    }
}
