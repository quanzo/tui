<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\async;

/**
 * DTO записи о завершённой задаче (для ring-buffer `recentFinished`).
 *
 * Важно: этот DTO хранит только примитивы (строки/числа), чтобы избежать случайных shallow-copy проблем
 * при хранении объектов из async-контекста.
 */
final class RecentJobRecord
{
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $jobId,
        public readonly string $label,
        public readonly string $status,
        public readonly int $startedAtMs,
        public readonly int $finishedAtMs,
    ) {
    }

    public function getDurationMs(): int
    {
        return max(0, $this->finishedAtMs - $this->startedAtMs);
    }
}
