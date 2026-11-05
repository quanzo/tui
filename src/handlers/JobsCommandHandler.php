<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\helpers\TuiTimeHelper;
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
     * Возвращает справку команды `/jobs`.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto
    {
        return new TuiCommandHelpDto([
            new TuiHelpRowDto('/jobs', 'Активные и недавние задачи. Колонка startedAt — локальное время'),
        ]);
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
                TuiTimeHelper::formatEpochMs($active->startedAtMs),
                $this->formatDurationMs($active->runningForMs),
                '',
            ];
        }

        foreach ($this->jobs->listRecentFinished() as $finished) {
            $rows[] = [
                $finished->jobId,
                $finished->label,
                $finished->status,
                TuiTimeHelper::formatEpochMs($finished->startedAtMs),
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

    /**
     * Форматирует длительность в миллисекундах для колонок `runningFor` и `duration`.
     *
     * Короче секунды значение остаётся в `ms`, иначе печатаются секунды с двумя знаками.
     *
     * @param int $ms Длительность в миллисекундах, включая 0
     * @return string
     */
    private function formatDurationMs(int $ms): string
    {
        if ($ms < 1000) {
            return $ms . ' ms';
        }

        return number_format($ms / 1000, 2, '.', '') . ' s';
    }
}
