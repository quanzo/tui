<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\help;

/**
 * Сводная таблица команд для руководства: строки всех зарегистрированных handlers.
 *
 * Пример использования:
 *
 * ```php
 * $catalog = new TuiCommandHelpCatalogDto([
 *     new TuiHelpRowDto('/help', 'Руководство'),
 * ]);
 * ```
 */
final class TuiCommandHelpCatalogDto
{
    /** @var list<TuiHelpRowDto> */
    private array $rows;

    /**
     * @param list<TuiHelpRowDto> $rows Строки в порядке показа
     */
    public function __construct(array $rows = [])
    {
        $this->rows = $this->copyRows($rows);
    }

    /**
     * Копирует строки каталога.
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->rows = $this->copyRows($this->rows);
    }

    /**
     * Возвращает копии строк каталога.
     *
     * @return list<TuiHelpRowDto>
     */
    public function getRows(): array
    {
        return $this->copyRows($this->rows);
    }

    /**
     * Заменяет строки каталога своими копиями.
     *
     * @param list<TuiHelpRowDto> $rows Новые строки
     * @return self
     */
    public function setRows(array $rows): self
    {
        $this->rows = $this->copyRows($rows);
        return $this;
    }

    /**
     * Клонирует каждую строку.
     *
     * @param list<TuiHelpRowDto> $rows Исходные строки
     * @return list<TuiHelpRowDto>
     */
    private function copyRows(array $rows): array
    {
        $copied = [];
        foreach ($rows as $row) {
            $copied[] = clone $row;
        }

        return $copied;
    }
}
