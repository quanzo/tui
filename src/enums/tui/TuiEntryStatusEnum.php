<?php

declare(strict_types=1);

namespace quanzo\tui\enums\tui;

/**
 * Enum статусов записи истории TUI.
 *
 * Пример использования:
 *
 * ```php
 * $status = TuiEntryStatusEnum::Ok;
 * ```
 */
enum TuiEntryStatusEnum: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Info = 'info';
}
