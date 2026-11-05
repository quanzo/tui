<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/break`.
 *
 * Назначение:
 * - позволяет пользователю запросить отмену выполняющейся асинхронной команды;
 * - при параллельном запуске нескольких задач поддерживает `/break <jobId>`.
 *
 * Семантика отмены:
 * - отмена кооперативная (Amp cancellation): задача должна реагировать на cancellation;
 * - best-effort “жёсткое” прерывание возможно, если конкретная задача зарегистрировала forceCancel callback.
 *
 * Пример использования:
 *
 * ```php
 * $jobs = new TuiJobManager();
 * $handler = new BreakCommandHandler($jobs);
 * $handler->handle($ctx, new ParsedUserInputDto('/break', true, 'break', []));
 * ```
 */
final class BreakCommandHandler implements TuiCommandHandlerInterface
{
    public function __construct(private readonly TuiJobManager $jobs)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'break';
    }

    /**
     * Возвращает справку команды `/break`.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto
    {
        return new TuiCommandHelpDto([
            new TuiHelpRowDto('/break', 'Отменить последнюю активную задачу. Нет задач — сообщение «Нет активных задач.»'),
            new TuiHelpRowDto('/break job_1', 'Отменить задачу с указанным id. Нет такого id — сообщение «Задача не найдена.»'),
        ]);
    }

    /**
     * Отменяет задачу по jobId или последнюю активную, если jobId не указан.
     *
     * UX:
     * - если задач нет — выводим понятное сообщение и подсказку по активным jobId;
     * - если jobId не найден — выводим ошибку и список активных jobId.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $args = $input->getArgs();
        $jobId = isset($args[0]) ? trim((string) $args[0]) : null;
        $jobId = ($jobId === '') ? null : $jobId;

        $active = $this->jobs->listActiveJobIds();
        if ($active === []) {
            $entry = TuiHistoryEntryDto::output('break none')
                ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Нет активных задач.')])
                ->setPlainText('no active jobs');
            return (new TuiCommandResultDto())->setAppendEntries([$entry]);
        }

        $ok = $this->jobs->break($jobId);
        if (!$ok) {
            $entry = TuiHistoryEntryDto::output('break not found')
                ->setBlocks([new NoticeBlockDto(
                    TuiNoticeKindEnum::Error,
                    'Задача не найдена. Активные jobId: ' . implode(', ', $active),
                )])
                ->setPlainText('job not found');
            return (new TuiCommandResultDto())->setAppendEntries([$entry]);
        }

        $target = $jobId ?? '(last active)';
        $entry = TuiHistoryEntryDto::output('break requested')
            ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Warning, 'Отмена запрошена: ' . $target)])
            ->setPlainText('break requested');

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }
}
