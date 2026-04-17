<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

use Amp\Cancellation;
use Amp\Future;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\tui\async\TuiJobManager;

/**
 * Контракт асинхронного handler'а TUI-команды (Amp v3).
 *
 * Зачем это нужно:
 * - синхронный `handle()` блокирует TUI на время выполнения команды;
 * - async-handler возвращает `Future`, позволяя TUI продолжать принимать ввод и отрисовываться;
 * - `Cancellation` используется для кооперативного прерывания (например, через `/break`).
 *
 * Важно:
 * - отмена в Amp кооперативная: handler должен либо ожидать операции, поддерживающей cancellation,
 *   либо периодически проверять состояние отмены и корректно завершаться.
 *
 * Пример использования:
 *
 * ```php
 * final class LongCommandHandler implements AsyncTuiCommandHandlerInterface
 * {
 *     public function getName(): string { return 'long'; }
 *
 *     public function handleAsync(TuiCommandContextDto $ctx, ParsedUserInputDto $input, Cancellation $cancellation): Future
 *     {
 *         return Amp\\async(function () use ($cancellation) {
 *             // ... work ...
 *             return new TuiCommandResultDto();
 *         });
 *     }
 * }
 * ```
 */
interface AsyncTuiCommandHandlerInterface extends TuiNamedCommandHandlerInterface
{
    /**
     * Асинхронно обрабатывает команду и возвращает `Future` результата.
     *
     * @param TuiCommandContextDto $ctx Контекст выполнения (cwd и т.д.)
     * @param ParsedUserInputDto $input Результат парсинга пользовательского ввода
     * @param Cancellation $cancellation Токен отмены (например, для `/break`)
     * @return Future<TuiCommandResultDto>
     */
    public function handleAsync(
        TuiCommandContextDto $ctx,
        ParsedUserInputDto $input,
        Cancellation $cancellation,
    ): Future;

    /**
     * Возвращает best-effort callback “жёсткого” прерывания, если handler управляет внешним ресурсом.
     *
     * Пример: handler запускает процесс и может послать SIGTERM/SIGKILL в ответ на `/break`.\n
     * Возвращаемое замыкание будет передано в {@see TuiJobManager::start()} как `$forceCancel`.
     *
     * По умолчанию возвращает `null`.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return callable():void|null
     */
    public function getForceCancelCallback(TuiCommandContextDto $ctx, ParsedUserInputDto $input): ?callable;
}
