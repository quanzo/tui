<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\hooks;

use quanzo\tui\classes\dto\tui\TuiPreHookDecisionDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\interfaces\tui\TuiPreOutputHookInterface;

/**
 * Дефолтный pre-hook для TUI: выводит текст как есть.
 *
 * Используется как fallback, если `InteractiveCommand` не сконфигурирована внешним pre-hook.
 *
 * Пример использования:
 *
 * ```php
 * $hook = new DefaultTuiPreOutputHook();
 * $decision = $hook->decide("hello");
 * ```
 */
final class DefaultTuiPreOutputHook implements TuiPreOutputHookInterface
{
    /**
     * {@inheritDoc}
     */
    public function decide(string $originalInput): TuiPreHookDecisionDto
    {
        return (new TuiPreHookDecisionDto($originalInput))
            ->setAppendEntries([TuiHistoryEntryDto::output($originalInput)]);
    }
}
