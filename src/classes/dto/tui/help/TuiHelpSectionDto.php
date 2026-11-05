<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\help;

/**
 * Секция пользовательской справки: заголовок, абзацы, список и необязательная таблица.
 *
 * Пример использования:
 *
 * ```php
 * $section = (new TuiHelpSectionDto('Режимы'))
 *     ->setItems(['ВВОД — набор текста']);
 * ```
 */
final class TuiHelpSectionDto
{
    /** @var list<string> */
    private array $paragraphs;

    /** @var list<string> */
    private array $items;

    /**
     * @param string $title Заголовок секции
     * @param list<string> $paragraphs Абзацы под заголовком
     * @param list<string> $items Пункты маркированного списка
     * @param TuiHelpTableDto|null $table Таблица секции или null
     */
    public function __construct(
        private string $title,
        array $paragraphs = [],
        array $items = [],
        private ?TuiHelpTableDto $table = null,
    ) {
        $this->paragraphs = array_values($paragraphs);
        $this->items = array_values($items);
        if ($this->table !== null) {
            $this->table = clone $this->table;
        }
    }

    /**
     * Копирует вложенную таблицу, абзацы и пункты списка остаются строками.
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->paragraphs = array_values($this->paragraphs);
        $this->items = array_values($this->items);
        if ($this->table !== null) {
            $this->table = clone $this->table;
        }
    }

    /**
     * Возвращает заголовок секции.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Задаёт заголовок секции.
     *
     * @param string $title Текст заголовка
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Возвращает абзацы секции.
     *
     * @return list<string>
     */
    public function getParagraphs(): array
    {
        return $this->paragraphs;
    }

    /**
     * Заменяет абзацы секции.
     *
     * @param list<string> $paragraphs Новые абзацы
     * @return self
     */
    public function setParagraphs(array $paragraphs): self
    {
        $this->paragraphs = array_values($paragraphs);
        return $this;
    }

    /**
     * Возвращает пункты маркированного списка.
     *
     * @return list<string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Заменяет пункты маркированного списка.
     *
     * @param list<string> $items Новые пункты
     * @return self
     */
    public function setItems(array $items): self
    {
        $this->items = array_values($items);
        return $this;
    }

    /**
     * Возвращает таблицу секции.
     *
     * @return TuiHelpTableDto|null
     */
    public function getTable(): ?TuiHelpTableDto
    {
        return $this->table;
    }

    /**
     * Задаёт таблицу секции.
     *
     * В секцию кладётся копия, чтобы дальнейшие правки исходной таблицы её не меняли.
     *
     * @param TuiHelpTableDto|null $table Таблица или null, чтобы убрать её
     * @return self
     */
    public function setTable(?TuiHelpTableDto $table): self
    {
        $this->table = $table === null ? null : clone $table;
        return $this;
    }
}
