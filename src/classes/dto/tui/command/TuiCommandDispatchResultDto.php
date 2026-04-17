<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\command;

use quanzo\tui\interfaces\tui\command\AsyncTuiCommandHandlerInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * DTO результата диспетчеризации команды.
 *
 * Этот DTO описывает только *выбор* handler'а и тип исполнения:
 * - sync: handler + готовый `TuiCommandResultDto`;
 * - async: handler, который нужно запустить через Amp и получить результат позже.
 *
 * Почему не возвращаем напрямую `TuiCommandResultDto`:
 * - `WorkspaceTuiPreOutputHook` должен уметь стартовать async-команды и сразу возвращать
 *   в UI entry “запущено…”, а результат доставлять позже (через JobManager).
 *
 * Пример использования:
 *
 * ```php
 * $dispatch = $dispatcher->dispatch($ctx, $parsed);
 * if ($dispatch->isAsync()) { ... } else { $result = $dispatch->getSyncResult(); }
 * ```
 */
final class TuiCommandDispatchResultDto
{
    private ?TuiCommandResultDto $syncResult = null;
    private ?TuiCommandHandlerInterface $syncHandler = null;
    private ?AsyncTuiCommandHandlerInterface $asyncHandler = null;

    /**
     * @param TuiCommandResultDto $result
     * @param TuiCommandHandlerInterface $handler
     * @return self
     */
    public static function sync(TuiCommandResultDto $result, TuiCommandHandlerInterface $handler): self
    {
        $self = new self();
        $self->syncResult = $result;
        $self->syncHandler = $handler;
        return $self;
    }

    /**
     * @param AsyncTuiCommandHandlerInterface $handler
     * @return self
     */
    public static function async(AsyncTuiCommandHandlerInterface $handler): self
    {
        $self = new self();
        $self->asyncHandler = $handler;
        return $self;
    }

    public function isAsync(): bool
    {
        return $this->asyncHandler !== null;
    }

    public function isHandled(): bool
    {
        return $this->syncResult !== null || $this->asyncHandler !== null;
    }

    public function getSyncResult(): ?TuiCommandResultDto
    {
        return $this->syncResult;
    }

    public function getSyncHandler(): ?TuiCommandHandlerInterface
    {
        return $this->syncHandler;
    }

    public function getAsyncHandler(): ?AsyncTuiCommandHandlerInterface
    {
        return $this->asyncHandler;
    }
}
