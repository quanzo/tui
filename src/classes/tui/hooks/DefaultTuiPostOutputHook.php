<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\hooks;

use quanzo\tui\classes\dto\tui\PostOutputContextDto;
use quanzo\tui\interfaces\tui\TuiPostOutputHookInterface;

/**
 * Дефолтный post-hook для TUI: возвращает текст + дату/время.
 *
 * Возвращаемое значение будет добавлено в history и отрисовано на следующем кадре.
 *
 * Пример использования:
 *
 * ```php
 * $hook = new DefaultTuiPostOutputHook();
 * $extra = $hook->afterRender(new PostOutputContextDto('in', 'out'));
 * ```
 */
final class DefaultTuiPostOutputHook implements TuiPostOutputHookInterface
{
    /**
     * {@inheritDoc}
     */
    public function afterRender(PostOutputContextDto $ctx): ?string
    {
        $ts = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        return $ctx->getRenderedOutput() . "\n\n" . "Дата: {$ts}";
    }
}
