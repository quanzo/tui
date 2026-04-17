<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\async;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Future;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;

/**
 * Менеджер асинхронных TUI-задач (jobs) на базе Amp v3.
 *
 * Задача менеджера:
 * - хранить список запущенных команд (можно несколько параллельно);
 * - позволять отменять задачу через `/break` (кооперативно + best-effort force cancel);
 * - превращать завершённые/отменённые/упавшие задачи в понятные entries истории, которые UI может добавить в вывод.
 *
 * Ограничения / соглашения:
 * - Менеджер не делает IO и не пишет напрямую в терминал.
 * - Менеджер не “крутит” event loop: он рассчитан на то, что TUI уже работает внутри Revolt EventLoop
 *   (см. `InteractiveCommand`).
 * - Cancel semantics в Amp кооперативные: `cancel()` является запросом на отмену.
 *   Если handler не реагирует — задача может не остановиться мгновенно.
 *
 * Пример использования:
 *
 * ```php
 * $jobs = new TuiJobManager();
 * $jobId = $jobs->start('/long 5', fn(Cancellation $c) => $handler->handleAsync($ctx, $input, $c));
 * // ... later in the render tick ...
 * foreach ($jobs->drainCompleted() as $entry) { $history->append($entry); }
 * ```
 */
final class TuiJobManager
{
    /** @var array<string, RunningJob> */
    private array $jobs = [];

    /** @var list<string> */
    private array $startOrder = [];

    /**
     * Последние завершённые задачи (ring-buffer).
     *
     * @var list<RecentJobRecord>
     */
    private array $recentFinished = [];

    /**
     * Максимальный размер ring-buffer'а завершённых задач.
     */
    private int $recentLimit = 20;

    private int $seq = 0;

    /**
     * Запускает асинхронную задачу.
     *
     * @param string $label Человекочитаемая метка (обычно оригинальный ввод, например `/long 10`)
     * @param callable(\Amp\Cancellation):Future<TuiCommandResultDto> $startFn Фабрика Future, получающая Cancellation
     * @param callable():void|null $forceCancel Best-effort “жёсткое” прерывание внешнего ресурса (если есть)
     * @return string jobId
     */
    public function start(string $label, callable $startFn, ?callable $forceCancel = null): string
    {
        $this->seq++;
        $jobId = 'job_' . $this->seq;
        $startedAtMs = $this->nowMs();

        $deferred = new DeferredCancellation();
        $cancellation = $deferred->getCancellation();

        /** @var Future<TuiCommandResultDto> $future */
        $future = $startFn($cancellation);
        $future->ignore();

        $forceCancelClosure = $forceCancel !== null ? \Closure::fromCallable($forceCancel) : null;
        $this->jobs[$jobId] = new RunningJob($jobId, $label, $future, $deferred, $forceCancelClosure, $startedAtMs);
        $this->startOrder[] = $jobId;

        return $jobId;
    }

    /**
     * Отменяет задачу.
     *
     * Если `$jobId` не задан — отменяет последнюю (по времени старта) живую задачу.
     *
     * @param string|null $jobId
     * @return bool true если задача найдена и отмена запрошена
     */
    public function break(?string $jobId = null): bool
    {
        if ($jobId === null || $jobId === '') {
            $jobId = $this->findLastActiveJobId();
            if ($jobId === null) {
                return false;
            }
        }

        $job = $this->jobs[$jobId] ?? null;
        if ($job === null) {
            return false;
        }

        $job->requestCancel();
        return true;
    }

    /**
     * Возвращает список завершённых задач в виде entries истории и удаляет их из менеджера.
     *
     * Эта операция рассчитана на периодический вызов из TUI цикла (например, перед рендером кадра).
     *
     * @return list<TuiHistoryEntryDto>
     */
    public function drainCompleted(): array
    {
        $out = [];

        foreach ($this->jobs as $jobId => $job) {
            if (!$job->isComplete()) {
                continue;
            }

            unset($this->jobs[$jobId]);

            $finishedAtMs = $this->nowMs();
            $durationMs = $job->getRunningForMs($finishedAtMs);

            try {
                $res = $job->await();
                foreach ($res->getAppendEntries() as $e) {
                    $out[] = $e;
                }

                $this->pushRecent(new RecentJobRecord(
                    jobId: $jobId,
                    label: $job->getLabel(),
                    status: RecentJobRecord::STATUS_DONE,
                    startedAtMs: $job->getStartedAtMs(),
                    finishedAtMs: $finishedAtMs,
                ));

                $out[] = TuiHistoryEntryDto::output('job done meta')
                    ->setBlocks([new NoticeBlockDto(
                        TuiNoticeKindEnum::Info,
                        'Готово: ' . $job->getLabel() . ' (' . $jobId . ') за ' . $this->formatDurationMs($durationMs),
                    )])
                    ->setPlainText('done ' . $jobId);
                continue;
            } catch (CancelledException) {
                $this->pushRecent(new RecentJobRecord(
                    jobId: $jobId,
                    label: $job->getLabel(),
                    status: RecentJobRecord::STATUS_CANCELLED,
                    startedAtMs: $job->getStartedAtMs(),
                    finishedAtMs: $finishedAtMs,
                ));
                $out[] = TuiHistoryEntryDto::output('job cancelled')
                    ->setBlocks([new NoticeBlockDto(
                        TuiNoticeKindEnum::Warning,
                        'Отменено: ' . $job->getLabel() . ' (' . $jobId . ') за ' . $this->formatDurationMs($durationMs),
                    )])
                    ->setPlainText('cancelled ' . $jobId);
                continue;
            } catch (\Throwable $e) {
                $this->pushRecent(new RecentJobRecord(
                    jobId: $jobId,
                    label: $job->getLabel(),
                    status: RecentJobRecord::STATUS_FAILED,
                    startedAtMs: $job->getStartedAtMs(),
                    finishedAtMs: $finishedAtMs,
                ));
                $out[] = TuiHistoryEntryDto::output('job failed')
                    ->setBlocks([new NoticeBlockDto(
                        TuiNoticeKindEnum::Error,
                        'Ошибка команды: ' . $job->getLabel() . ' (' . $jobId . ') за ' . $this->formatDurationMs($durationMs) . ': ' . $e->getMessage(),
                    )])
                    ->setPlainText('failed ' . $jobId);
            }
        }

        return $out;
    }

    /**
     * Возвращает список активных jobId (для UX и `/break`).
     *
     * @return list<string>
     */
    public function listActiveJobIds(): array
    {
        $ids = [];
        foreach ($this->jobs as $jobId => $job) {
            if (!$job->isComplete()) {
                $ids[] = $jobId;
            }
        }
        return $ids;
    }

    /**
     * Возвращает snapshot активных задач.
     *
     * @return list<ActiveJobSnapshot>
     */
    public function listActiveJobs(): array
    {
        $now = $this->nowMs();
        $out = [];
        foreach ($this->jobs as $jobId => $job) {
            if ($job->isComplete()) {
                continue;
            }
            $out[] = new ActiveJobSnapshot(
                jobId: $jobId,
                label: $job->getLabel(),
                startedAtMs: $job->getStartedAtMs(),
                runningForMs: $job->getRunningForMs($now),
            );
        }
        return $out;
    }

    /**
     * Возвращает последние завершённые задачи (свежие в конце).
     *
     * @return list<RecentJobRecord>
     */
    public function listRecentFinished(): array
    {
        return $this->recentFinished;
    }

    private function pushRecent(RecentJobRecord $record): void
    {
        $this->recentFinished[] = $record;
        if (count($this->recentFinished) > $this->recentLimit) {
            $this->recentFinished = array_slice($this->recentFinished, -$this->recentLimit);
        }
    }

    private function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function formatDurationMs(int $ms): string
    {
        if ($ms < 1000) {
            return $ms . ' ms';
        }

        return number_format($ms / 1000, 2, '.', '') . ' s';
    }

    private function findLastActiveJobId(): ?string
    {
        for ($i = count($this->startOrder) - 1; $i >= 0; $i--) {
            $jobId = (string) ($this->startOrder[$i] ?? '');
            if ($jobId === '') {
                continue;
            }
            $job = $this->jobs[$jobId] ?? null;
            if ($job !== null && !$job->isComplete()) {
                return $jobId;
            }
        }

        return null;
    }
}
