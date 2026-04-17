<?php

declare(strict_types=1);

namespace quanzo\tui\enums\tui;

/**
 * Enum типов записей истории TUI.
 *
 * Используется модель Variant C (entries/frames): одна запись — атом истории,
 * который может быть свёрнут/скопирован/отфильтрован.
 *
 * Пример использования:
 *
 * ```php
 * $kind = TuiHistoryEntryKindEnum::Output;
 * ```
 */
enum TuiHistoryEntryKindEnum: string
{
    case UserInput = 'user_input';
    case Output = 'output';
    case Event = 'event';
}
