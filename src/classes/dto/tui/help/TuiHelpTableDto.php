<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\help;

/**
 * Таблица пользовательской справки.
 *
 * Две колонки, если заголовок области пустой: подпись и описание.
 * Три колонки, если `scopeHeader` задан: подпись, где работает, описание.
 *
 * Пример использования:
 *
 * ```php
 * $table = (new TuiHelpTableDto('Команда', 'Описание'))
 *     ->setRows([new TuiHelpRowDto('/help', 'Справка')]);
 * ```
 */
final class TuiHelpTableDto
{
    /** @var list<TuiHelpRowDto> */
    private array $rows;

    /**
     * @param string $labelHeader Заголовок первой колонки
     * @param string $descriptionHeader Заголовок последней колонки
     * @param string $scopeHeader Заголовок средней колонки; пустая строка — таблицы из двух колонок
     * @param list<TuiHelpRowDto> $rows Строки таблицы
     */
    public function __construct(
        private string $labelHeader,
        private string $descriptionHeader,
        private string $scopeHeader = '',
        array $rows = [],
    ) {
        $this->rows = $this->copyRows($rows);
    }

    /**
     * Копирует таблицу вместе со строками, чтобы исходные объекты не менялись снаружи.
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->rows = $this->copyRows($this->rows);
    }

    /**
     * Возвращает заголовок первой колонки.
     *
     * @return string
     */
    public function getLabelHeader(): string
    {
        return $this->labelHeader;
    }

    /**
     * Задаёт заголовок первой колонки.
     *
     * @param string $labelHeader Например, «Клавиша» или «Команда»
     * @return self
     */
    public function setLabelHeader(string $labelHeader): self
    {
        $this->labelHeader = $labelHeader;
        return $this;
    }

    /**
     * Возвращает заголовок колонки описания.
     *
     * @return string
     */
    public function getDescriptionHeader(): string
    {
        return $this->descriptionHeader;
    }

    /**
     * Задаёт заголовок колонки описания.
     *
     * @param string $descriptionHeader Текст заголовка
     * @return self
     */
    public function setDescriptionHeader(string $descriptionHeader): self
    {
        $this->descriptionHeader = $descriptionHeader;
        return $this;
    }

    /**
     * Возвращает заголовок средней колонки.
     *
     * @return string Пустая строка, если колонки «где» нет
     */
    public function getScopeHeader(): string
    {
        return $this->scopeHeader;
    }

    /**
     * Задаёт заголовок средней колонки.
     *
     * @param string $scopeHeader Пустая строка выключает колонку «где»
     * @return self
     */
    public function setScopeHeader(string $scopeHeader): self
    {
        $this->scopeHeader = $scopeHeader;
        return $this;
    }

    /**
     * Сообщает, есть ли у таблицы колонка области действия.
     *
     * @return bool
     */
    public function hasScopeColumn(): bool
    {
        return $this->scopeHeader !== '';
    }

    /**
     * Возвращает строки таблицы.
     *
     * @return list<TuiHelpRowDto>
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Заменяет строки таблицы своими копиями.
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
     * Клонирует каждую строку, чтобы правка снаружи не меняла таблицу.
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
