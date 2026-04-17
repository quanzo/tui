<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\async;

use Amp\DeferredCancellation;
use Amp\Future;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;

/**
 * Внутренняя модель выполняющейся задачи.
 *
 * Вынесена в отдельный класс, чтобы:
 * - соблюдать PSR-12 (один класс на файл);
 * - не раздувать публичный API `TuiJobManager` служебными деталями.
 *
 * @internal
 */
final class RunningJob
{
    /**
     * @param string $jobId
     * @param string $label
     * @param Future<TuiCommandResultDto> $future
     * @param DeferredCancellation $cancellation
     * @param \Closure():void|null $forceCancel
     */
    public function __construct(
        private readonly string $jobId,
        private readonly string $label,
        private readonly Future $future,
        private readonly DeferredCancellation $cancellation,
        private readonly ?\Closure $forceCancel,
        private readonly int $startedAtMs,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getStartedAtMs(): int
    {
        return $this->startedAtMs;
    }

    public function getRunningForMs(int $nowMs): int
    {
        return max(0, $nowMs - $this->startedAtMs);
    }

    public function isComplete(): bool
    {
        return $this->future->isComplete();
    }

    /**
     * @return TuiCommandResultDto
     */
    public function await(): TuiCommandResultDto
    {
        /** @var TuiCommandResultDto $res */
        $res = $this->future->await();
        return $res;
    }

    public function requestCancel(): void
    {
        $this->cancellation->cancel();
        if ($this->forceCancel !== null) {
            try {
                ($this->forceCancel)();
            } catch (\Throwable) {
                // best-effort
            }
        }
    }
}
