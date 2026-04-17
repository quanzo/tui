<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\command\handlers;

use Amp\Cancellation;
use Amp\Future;
use quanzo\tui\classes\dto\tui\async\ShellProcessResultDto;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\CodeBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\async\ShellProcessRunnerInterface;
use quanzo\tui\interfaces\tui\command\AsyncTuiCommandHandlerInterface;

use function Amp\async;

/**
 * Handler команды `/exec` — запуск shell-команды асинхронно.
 *
 * Поведение:
 * - запускает команду через runner (обычно `bash -lc <cmd>`);\n
 * - по завершению добавляет в history итоговый stdout/stderr и exit code;\n
 * - отмена происходит через `/break` (cancellation + best-effort force cancel на уровне runner).\n
 */
final class ExecCommandHandler implements AsyncTuiCommandHandlerInterface
{
    public function __construct(private readonly ShellProcessRunnerInterface $runner)
    {
    }

    /**
     * Последний запущенный процесс.
     *
     * Поле нужно только для того, чтобы отдать `forceCancel` в `WorkspaceTuiPreOutputHook`.\n
     * С точки зрения UX это приемлемо: `/exec` создаёт job, а `/break` может её остановить.\n
     */
    private ?\quanzo\tui\classes\tui\async\ShellRunningProcessDto $lastRunning = null;

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'exec';
    }

    /**
     * Запускает shell-команду и возвращает `Future` результата.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @param Cancellation $cancellation
     * @return Future<TuiCommandResultDto>
     */
    public function handleAsync(TuiCommandContextDto $ctx, ParsedUserInputDto $input, Cancellation $cancellation): Future
    {
        $cmd = $this->buildCommand($input);
        if ($cmd === '') {
            return Future::complete((new TuiCommandResultDto())->setAppendEntries([
                TuiHistoryEntryDto::output('exec empty')
                    ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Warning, 'Использование: /exec <command...>')])
                    ->setPlainText('exec empty'),
            ]));
        }

        $running = $this->runner->start($cmd, $cancellation);
        $this->lastRunning = $running;

        return async(function () use ($running): TuiCommandResultDto {
            $res = $running->getFuture()->await();
            return $this->mapResultToTui($res);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getForceCancelCallback(TuiCommandContextDto $ctx, ParsedUserInputDto $input): ?callable
    {
        $running = $this->lastRunning;
        if ($running === null) {
            return null;
        }

        return static fn() => $running->forceCancel();
    }

    private function buildCommand(ParsedUserInputDto $input): string
    {
        $args = $input->getArgs();
        $cmd = trim(implode(' ', $args));
        return $cmd;
    }

    private function mapResultToTui(ShellProcessResultDto $res): TuiCommandResultDto
    {
        $blocks = [];

        $summary = 'exitCode=' . $res->getExitCode();
        if ($res->wasTruncated()) {
            $summary .= ' (truncated)';
        }

        $blocks[] = new NoticeBlockDto(TuiNoticeKindEnum::Info, $summary);

        $stdout = $res->getStdout();
        if (trim($stdout) !== '') {
            $blocks[] = (new CodeBlockDto($stdout))->setLanguage('stdout');
        }

        $stderr = $res->getStderr();
        if (trim($stderr) !== '') {
            $blocks[] = (new CodeBlockDto($stderr))->setLanguage('stderr');
        }

        if ($blocks === []) {
            $blocks[] = new NoticeBlockDto(TuiNoticeKindEnum::Info, 'no output');
        }

        $panel = new PanelBlockDto('exec: ' . $res->getCommand(), $blocks);

        $entry = TuiHistoryEntryDto::output('exec')
            ->setBlocks([$panel])
            ->setPlainText('exec');

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }
}
