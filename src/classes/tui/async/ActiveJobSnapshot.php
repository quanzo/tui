<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\async;

/**
 * DTO snapshot активной задачи.
 *
 * Используется для команды `/jobs`: активные задачи должны показывать время старта
 * и текущую длительность выполнения.
 */
final class ActiveJobSnapshot
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $label,
        public readonly int $startedAtMs,
        public readonly int $runningForMs,
    ) {
    }
}
