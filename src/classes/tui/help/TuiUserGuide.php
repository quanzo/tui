<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\help;

use quanzo\tui\classes\dto\tui\help\TuiCommandHelpCatalogDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpGuideDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpSectionDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpTableDto;

/**
 * Сборщик пользовательского руководства TUI.
 *
 * Экран, режимы, клавиши и фоновые задачи описаны здесь. Строки команд приходят
 * снаружи: их сообщают сами handlers через `getHelp()`.
 *
 * Пример использования:
 *
 * ```php
 * $guide = TuiUserGuide::build(new TuiCommandHelpCatalogDto([]));
 * $plain = TuiUserGuide::toPlainText($guide);
 * ```
 */
final class TuiUserGuide
{
    /**
     * Собирает руководство и подставляет переданные строки команд.
     *
     * @param TuiCommandHelpCatalogDto $commands Строки раздела «Команды»
     * @return TuiHelpGuideDto
     */
    public static function build(TuiCommandHelpCatalogDto $commands): TuiHelpGuideDto
    {
        return new TuiHelpGuideDto(
            'Neuron TUI',
            'Длинный вывод читается в режиме «ПРОСМОТР»: Tab переключает фокус, стрелки и PgUp/PgDn листают историю, повторный Tab возвращает в «ВВОД».',
            [
                self::screenSection(),
                self::modesSection(),
                self::keysSection(),
                self::commandsSection($commands),
                self::jobsSection(),
            ],
            ['Tab: режим', 'Enter: отправить', 'Ctrl+C: выход', 'F2: мышь', '/help'],
        );
    }

    /**
     * Печатает руководство плоским текстом для справки Symfony.
     *
     * Флаг `NEURON_TUI_HELP_ASCII` здесь не учитывается: он меняет только панель `/help`.
     *
     * @param TuiHelpGuideDto $guide Руководство из {@see build()}
     * @return string Текст с завершающим переводом строки
     */
    public static function toPlainText(TuiHelpGuideDto $guide): string
    {
        $lines = [$guide->getTitle(), '', $guide->getIntro(), ''];
        foreach ($guide->getSections() as $section) {
            $lines[] = $section->getTitle();
            foreach ($section->getParagraphs() as $paragraph) {
                $lines[] = $paragraph;
            }
            foreach ($section->getItems() as $item) {
                $lines[] = '- ' . $item;
            }
            $table = $section->getTable();
            if ($table !== null) {
                $lines[] = self::formatTableHeader($table);
                foreach ($table->getRows() as $row) {
                    $lines[] = self::formatTableRow($table, $row);
                }
            }
            $lines[] = '';
        }

        if ($guide->getKeyHints() !== []) {
            $lines[] = implode(' | ', $guide->getKeyHints());
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * Секция про области экрана и то, что считается командой.
     *
     * @return TuiHelpSectionDto
     */
    private static function screenSection(): TuiHelpSectionDto
    {
        return new TuiHelpSectionDto('Экран', [
            'Сверху — история сообщений. Снизу — поле ввода: на экране видны 3 строки. В строке состояния: режим (ВВОД или ПРОСМОТР), MOUSE ON или MOUSE OFF, позиция курсора и число записей истории.',
            'Команды начинаются с /. Обычный текст и пустая команда / не выполняются: программа просит ввести /help. Неизвестная команда сообщает «Неизвестная команда».',
        ]);
    }

    /**
     * Секция про фокус ввода, просмотр истории и режим мыши.
     *
     * @return TuiHelpSectionDto
     */
    private static function modesSection(): TuiHelpSectionDto
    {
        return new TuiHelpSectionDto('Режимы', [], [
            'ВВОД — набор и правка текста, Enter отправляет команду. Клавиши прокрутки истории здесь не листают вывод.',
            'ПРОСМОТР — листается только история. Enter, буквы, Backspace и правка курсора в этом режиме ничего не делают.',
            'Мышь (F2) включается отдельно от фокуса. Клик по истории переводит в ПРОСМОТР, клик по полю ввода — в ВВОД и ставит курсор. Пока MOUSE ON, терминал забирает клики себе.',
        ]);
    }

    /**
     * Секция с таблицей сочетаний клавиш.
     *
     * @return TuiHelpSectionDto
     */
    private static function keysSection(): TuiHelpSectionDto
    {
        $table = (new TuiHelpTableDto('Клавиша', 'Действие', 'Где'))
            ->setRows([
                self::key('Tab', 'всегда', 'Переключить режим ВВОД / ПРОСМОТР'),
                self::key('Enter', 'ввод', 'Отправить команду. Пустая строка в историю команд не попадает, повтор последней команды не дублируется'),
                self::key('Ctrl+J', 'ввод', 'Новая строка без отправки. В терминалах с Kitty то же делает Ctrl+Enter. Обычный Ctrl+Enter часто неотличим от Enter'),
                self::key('Left / Right', 'ввод', 'Сдвинуть курсор на символ'),
                self::key('Home / End', 'ввод', 'В начало или в конец строки'),
                self::key('Backspace', 'ввод', 'Удалить символ слева от курсора'),
                self::key('Delete', 'ввод', 'Удалить символ под курсором'),
                self::key('Up / Down', 'ввод, первая строка', 'Предыдущая или следующая команда. Down после последней возвращает черновик'),
                self::key('Up / Down', 'ввод, не первая строка', 'Переместить курсор по строкам буфера'),
                self::key('Up / Down', 'просмотр', 'Прокрутить историю на одну строку'),
                self::key('PgUp / PgDn', 'просмотр', 'Прокрутить историю на страницу'),
                self::key('Вставка', 'ввод', 'Вставить текст в позицию курсора, переносы сохраняются'),
                self::key('F2', 'всегда', 'Включить или выключить мышь (MOUSE ON / MOUSE OFF)'),
                self::key('Ctrl+C', 'всегда', 'Выйти сразу. То же делает /exit'),
            ]);

        return (new TuiHelpSectionDto('Клавиши'))->setTable($table);
    }

    /**
     * Секция с таблицей команд, собранной из handlers.
     *
     * @param TuiCommandHelpCatalogDto $commands Строки `getHelp()` зарегистрированных команд
     * @return TuiHelpSectionDto
     */
    private static function commandsSection(TuiCommandHelpCatalogDto $commands): TuiHelpSectionDto
    {
        $table = (new TuiHelpTableDto('Команда', 'Описание'))
            ->setRows($commands->getRows());

        return (new TuiHelpSectionDto('Команды'))->setTable($table);
    }

    /**
     * Секция про запуск, ожидание и отмену фоновых задач.
     *
     * @return TuiHelpSectionDto
     */
    private static function jobsSection(): TuiHelpSectionDto
    {
        return new TuiHelpSectionDto('Фоновые задачи', [
            'После /exec и /long сразу печатается «Запущено: … (job_…)». Следующие команды можно вводить, не дожидаясь окончания.',
            'Итог, отмена или ошибка дописываются в историю вместе со временем выполнения.',
            'Отмена кооперативная: задача должна сама реагировать на /break. У /exec дополнительно посылается SIGTERM, затем SIGKILL.',
        ]);
    }

    /**
     * Строка таблицы клавиш.
     *
     * @param string $label Сочетание
     * @param string $scope Где сочетание работает
     * @param string $description Что происходит
     * @return TuiHelpRowDto
     */
    private static function key(string $label, string $scope, string $description): TuiHelpRowDto
    {
        return new TuiHelpRowDto($label, $description, $scope);
    }

    /**
     * Собирает строку заголовков таблицы для плоского текста.
     *
     * @param TuiHelpTableDto $table Таблица секции
     * @return string
     */
    private static function formatTableHeader(TuiHelpTableDto $table): string
    {
        if ($table->hasScopeColumn()) {
            return $table->getLabelHeader() . ' | ' . $table->getScopeHeader() . ' | ' . $table->getDescriptionHeader();
        }

        return $table->getLabelHeader() . ' | ' . $table->getDescriptionHeader();
    }

    /**
     * Собирает одну строку таблицы для плоского текста.
     *
     * @param TuiHelpTableDto $table Таблица, к которой относится строка
     * @param TuiHelpRowDto $row Строка
     * @return string
     */
    private static function formatTableRow(TuiHelpTableDto $table, TuiHelpRowDto $row): string
    {
        if ($table->hasScopeColumn()) {
            return $row->getLabel() . ' | ' . $row->getScope() . ' | ' . $row->getDescription();
        }

        return $row->getLabel() . ' | ' . $row->getDescription();
    }
}
