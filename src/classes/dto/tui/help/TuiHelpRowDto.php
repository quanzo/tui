<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\help;

/**
 * Строка таблицы пользовательской справки: подпись, необязательная колонка «где» и описание.
 *
 * Пустой `scope` означает строку из двух колонок (команда и описание).
 * Непустой `scope` — строку из трёх колонок (клавиша, режим, действие).
 *
 * Пример использования:
 *
 * ```php
 * $row = (new TuiHelpRowDto('Tab', 'Переключить режим'))->setScope('всегда');
 * ```
 */
final class TuiHelpRowDto
{
    /**
     * @param string $label Подпись строки: клавиша или пример команды
     * @param string $description Что делает клавиша или команда
     * @param string $scope Где действует клавиша; пустая строка, если колонки нет
     */
    public function __construct(
        private string $label,
        private string $description,
        private string $scope = '',
    ) {
    }

    /**
     * Возвращает подпись строки.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Задаёт подпись строки.
     *
     * @param string $label Клавиша или пример команды
     * @return self
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Возвращает описание действия.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Задаёт описание действия.
     *
     * @param string $description Текст для последней колонки
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Возвращает область действия клавиши.
     *
     * @return string Пустая строка, если у таблицы нет колонки «где»
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * Задаёт область действия клавиши.
     *
     * @param string $scope Например, «ввод» или «просмотр»; пустая строка убирает смысл колонки у строки
     * @return self
     */
    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }
}
