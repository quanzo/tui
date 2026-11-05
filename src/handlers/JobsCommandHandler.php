<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/jobs` — список асинхронных задач.
 *
 * Показывает:
 * - активные задачи (running) с длительностью выполнения на текущий момент;
 * - последние завершённые задачи (recent finished) с итоговой длительностью.\n
 *
 * Команда полезна в режиме параллельных async-команд: пользователь может посмотреть `jobId`
 * и отменить конкретную задачу через `/break <jobId>`.
 */
final class JobsCommandHandler implements TuiCommandHandlerInterface
{
    public function __construct(private readonly TuiJobManager $jobs)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'jobs';
    }

    /**
     * Формирует таблицу задач.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $rows = [];

        foreach ($this->jobs->listActiveJobs() as $active) {
            $rows[] = [
                $active->jobId,
                $active->label,
                'running',
                $this->formatStartedAt($active->startedAtMs),
                $this->formatDurationMs($active->runningForMs),
                '',
            ];
        }

        foreach ($this->jobs->listRecentFinished() as $finished) {
            $rows[] = [
                $finished->jobId,
                $finished->label,
                $finished->status,
                $this->formatStartedAt($finished->startedAtMs),
                '',
                $this->formatDurationMs($finished->getDurationMs()),
            ];
        }

        $table = (new TableBlockDto(
            headers: ['jobId', 'label', 'status', 'startedAt', 'runningFor', 'duration'],
            rows: $rows,
        ))->setCompact(true);

        $panel = new PanelBlockDto('Jobs', [$table]);

        $entry = TuiHistoryEntryDto::output('jobs')
            ->setBlocks([$panel])
            ->setPlainText('jobs');

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }

    private function formatStartedAt(int $startedAtMs): string
    {
        // Это “best effort” для UX: просто показываем epoch ms, чтобы было удобно сравнивать.
        // При желании можно заменить на человеческий формат (H:i:s), но тогда нужен “wall clock”.
        return (string) $startedAtMs;
    }

    private function formatDurationMs(int $ms): string
    {
        if ($ms < 1000) {
            return $ms . ' ms';
        }

        return number_format($ms / 1000, 2, '.', '') . ' s';
    }
}
