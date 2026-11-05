<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\help;

/**
 * Справка одной TUI-команды: ноль или больше строк «вызов — описание».
 *
 * Пустой список значит, что команда не попадает в таблицу `/help`.
 *
 * Пример использования:
 *
 * ```php
 * $help = new TuiCommandHelpDto([
 *     new TuiHelpRowDto('/clear', 'Очистить историю'),
 * ]);
 * ```
 */
final class TuiCommandHelpDto
{
    /** @var list<TuiHelpRowDto> */
    private array $entries;

    /**
     * @param list<TuiHelpRowDto> $entries Строки справки этой команды
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $this->copyEntries($entries);
    }

    /**
     * Копирует строки, чтобы клон не делил их с оригиналом.
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->entries = $this->copyEntries($this->entries);
    }

    /**
     * Возвращает копии строк справки.
     *
     * @return list<TuiHelpRowDto>
     */
    public function getEntries(): array
    {
        return $this->copyEntries($this->entries);
    }

    /**
     * Заменяет строки справки своими копиями.
     *
     * @param list<TuiHelpRowDto> $entries Новые строки
     * @return self
     */
    public function setEntries(array $entries): self
    {
        $this->entries = $this->copyEntries($entries);
        return $this;
    }

    /**
     * Клонирует каждую строку.
     *
     * @param list<TuiHelpRowDto> $entries Исходные строки
     * @return list<TuiHelpRowDto>
     */
    private function copyEntries(array $entries): array
    {
        $copied = [];
        foreach ($entries as $entry) {
            $copied[] = clone $entry;
        }

        return $copied;
    }
}
