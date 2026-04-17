<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui;

use quanzo\tui\classes\dto\tui\TuiPreHookDecisionDto;

/**
 * Контракт pre-hook для интерактивного TUI.
 *
 * Вызывается после ввода строки (Enter) и до добавления текста в историю/вывод.
 * Может изменить текст вывода или отменить вывод.
 *
 * Пример использования:
 *
 * ```php
 * $decision = $hook->decide($input);
 * foreach ($decision->getAppendEntries() as $entry) { ... }
 * ```
 */
interface TuiPreOutputHookInterface
{
    /**
     * Принимает решение о выводе.
     *
     * @param string $originalInput Исходная введённая строка (может быть многострочной).
     * @return TuiPreHookDecisionDto Решение: что добавить в историю/как управлять циклом.
     */
    public function decide(string $originalInput): TuiPreHookDecisionDto;
}
