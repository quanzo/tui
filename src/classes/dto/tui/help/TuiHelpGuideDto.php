<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\help;

/**
 * Пользовательское руководство TUI: заголовок, вступление, секции и короткие подсказки клавиш.
 *
 * Это единственная модель текста справки. Экран `/help` и `./bin/console help interactive`
 * строятся из одного и того же экземпляра.
 *
 * Пример использования:
 *
 * ```php
 * $guide = new TuiHelpGuideDto('Neuron TUI', 'Tab переключает режим.', []);
 * ```
 */
final class TuiHelpGuideDto
{
    /** @var list<TuiHelpSectionDto> */
    private array $sections;

    /** @var list<string> */
    private array $keyHints;

    /**
     * @param string $title Заголовок руководства
     * @param string $intro Как читать длинный вывод, показывается сразу под заголовком
     * @param list<TuiHelpSectionDto> $sections Секции по порядку
     * @param list<string> $keyHints Короткие подсказки внизу панели
     */
    public function __construct(
        private string $title,
        private string $intro,
        array $sections,
        array $keyHints = [],
    ) {
        $this->sections = $this->copySections($sections);
        $this->keyHints = array_values($keyHints);
    }

    /**
     * Копирует секции, чтобы клон руководства не делил таблицы с оригиналом.
     *
     * @return void
     */
    public function __clone(): void
    {
        $this->sections = $this->copySections($this->sections);
        $this->keyHints = array_values($this->keyHints);
    }

    /**
     * Возвращает заголовок руководства.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Задаёт заголовок руководства.
     *
     * @param string $title Текст заголовка панели
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Возвращает вступление.
     *
     * @return string
     */
    public function getIntro(): string
    {
        return $this->intro;
    }

    /**
     * Задаёт вступление.
     *
     * @param string $intro Текст сразу под заголовком
     * @return self
     */
    public function setIntro(string $intro): self
    {
        $this->intro = $intro;
        return $this;
    }

    /**
     * Возвращает секции в порядке показа.
     *
     * @return list<TuiHelpSectionDto>
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Заменяет секции своими копиями.
     *
     * @param list<TuiHelpSectionDto> $sections Новые секции
     * @return self
     */
    public function setSections(array $sections): self
    {
        $this->sections = $this->copySections($sections);
        return $this;
    }

    /**
     * Возвращает короткие подсказки клавиш.
     *
     * @return list<string>
     */
    public function getKeyHints(): array
    {
        return $this->keyHints;
    }

    /**
     * Заменяет короткие подсказки клавиш.
     *
     * @param list<string> $keyHints Фрагменты строки подсказок
     * @return self
     */
    public function setKeyHints(array $keyHints): self
    {
        $this->keyHints = array_values($keyHints);
        return $this;
    }

    /**
     * Клонирует каждую секцию.
     *
     * @param list<TuiHelpSectionDto> $sections Исходные секции
     * @return list<TuiHelpSectionDto>
     */
    private function copySections(array $sections): array
    {
        $copied = [];
        foreach ($sections as $section) {
            $copied[] = clone $section;
        }

        return $copied;
    }
}
