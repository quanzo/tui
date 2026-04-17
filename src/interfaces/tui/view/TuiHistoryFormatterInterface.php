<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\view;

use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\dto\tui\view\TuiThemeDto;

/**
 * Контракт форматтера истории TUI.
 *
 * Преобразует историю (entries/blocks) в плоский список строк для отрисовки
 * в области вывода.
 *
 * Пример использования:
 *
 * ```php
 * $lines = $formatter->toDisplayLines($history, $innerWidth, new TuiThemeDto());
 * ```
 */
interface TuiHistoryFormatterInterface
{
    /**
     * @param TuiHistoryDto $history
     * @param int $innerWidth ширина без рамок (width - 2)
     * @param TuiThemeDto $theme
     * @return list<string>
     */
    public function toDisplayLines(TuiHistoryDto $history, int $innerWidth, TuiThemeDto $theme): array;
}
