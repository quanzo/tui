<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\hooks;

use quanzo\tui\classes\dto\tui\TuiPreHookDecisionDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandDispatchResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TextBlockDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\classes\tui\command\TuiCommandDispatcher;
use quanzo\tui\classes\tui\command\UserInputParser;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\TuiPreOutputHookInterface;

/**
 * Pre-hook TUI для режима Workspace: парсер → dispatcher → handlers.
 *
 * В отличие от «жёстко зашитого» варианта, набор handlers задаётся извне через dispatcher.
 * Это позволяет на базе `InteractiveCommand` создавать разные TUI-команды с разной функциональностью.
 *
 * Пример использования:
 *
 * ```php
 * $dispatcher = new TuiCommandDispatcher([$help, $ws, $clear, $exit]);
 * $hook = new WorkspaceTuiPreOutputHook($dispatcher);
 * $decision = $hook->decide('/help');
 * ```
 */
final class WorkspaceTuiPreOutputHook implements TuiPreOutputHookInterface
{
    private UserInputParser $parser;
    private TuiCommandDispatcher $dispatcher;
    private TuiJobManager $jobs;

    /**
     * @param TuiCommandDispatcher $dispatcher Диспетчер с зарегистрированными handlers
     * @param TuiJobManager $jobs Менеджер асинхронных задач (для async-команд и `/break`)
     * @param UserInputParser|null $parser Парсер пользовательского ввода (по умолчанию создаётся внутри)
     */
    public function __construct(TuiCommandDispatcher $dispatcher, TuiJobManager $jobs, ?UserInputParser $parser = null)
    {
        $this->dispatcher = $dispatcher;
        $this->jobs = $jobs;
        $this->parser = $parser ?? new UserInputParser();
    }

    /**
     * {@inheritDoc}
     */
    public function decide(string $originalInput): TuiPreHookDecisionDto
    {
        $ctx = new TuiCommandContextDto((string) getcwd(), new TuiHistoryDto());
        $parsed = $this->parser->parse($originalInput);

        $entries = [];
        $entries[] = TuiHistoryEntryDto::userInput($originalInput)
            ->setBlocks([new TextBlockDto('> ' . $originalInput)])
            ->setPlainText($originalInput);

        $decision = new TuiPreHookDecisionDto($originalInput);

        if ($parsed->isCommand()) {
            if ($parsed->getCommandName() === null) {
                $entries[] = TuiHistoryEntryDto::output('empty command')
                    ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Введите /help')])
                    ->setPlainText('Введите /help');
                return $decision->setAppendEntries($entries);
            }

            $dispatch = $this->dispatcher->dispatch($ctx, $parsed);
            if (!$dispatch->isHandled()) {
                $entries[] = TuiHistoryEntryDto::output('unknown command')
                    ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Error, 'Неизвестная команда: /' . $parsed->getCommandName())])
                    ->setPlainText('Неизвестная команда');
                return $decision->setAppendEntries($entries);
            }

            if ($dispatch->isAsync()) {
                $asyncHandler = $dispatch->getAsyncHandler();
                if ($asyncHandler === null) {
                    $entries[] = TuiHistoryEntryDto::output('async handler missing')
                        ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Error, 'Ошибка: async-handler отсутствует')])
                        ->setPlainText('async handler missing');
                    return $decision->setAppendEntries($entries);
                }

                $forceCancel = $asyncHandler->getForceCancelCallback($ctx, $parsed);
                $jobId = $this->jobs->start(
                    $originalInput,
                    static fn(\Amp\Cancellation $c) => $asyncHandler->handleAsync($ctx, $parsed, $c),
                    $forceCancel,
                );

                $entries[] = TuiHistoryEntryDto::output('job started')
                    ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Запущено: ' . $originalInput . ' (' . $jobId . ')')])
                    ->setPlainText('job started ' . $jobId);

                return $decision->setAppendEntries($entries);
            }

            $res = $dispatch->getSyncResult() ?? (new \quanzo\tui\classes\dto\tui\command\TuiCommandResultDto());
            if ($res->getAppendEntries() === []) {
                $entries[] = TuiHistoryEntryDto::output('empty result')
                    ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Команда выполнена.')])
                    ->setPlainText('Команда выполнена');
            } else {
                foreach ($res->getAppendEntries() as $e) {
                    $entries[] = $e;
                }
            }

            $decision->setClearHistory($res->isClearHistory());
            $decision->setExit($res->isExit());
            return $decision->setAppendEntries($entries);
        }

        // Обычный текст: пока просто подсказываем про /help.
        $entries[] = TuiHistoryEntryDto::output('text')
            ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Введите /help или команду, например /ws ls')])
            ->setPlainText('Введите /help');

        return $decision->setAppendEntries($entries);
    }
}
