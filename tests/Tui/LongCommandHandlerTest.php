<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\CancelledException;
use Amp\DeferredCancellation;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\tui\command\handlers\LongCommandHandler;
use Revolt\EventLoop;

/**
 * Тесты для `/long` async-команды.
 *
 * Примечание: в тестах мы не ждём реальное время выполнения (секунды),
 * а проверяем, что отмена прерывает `delay()` быстро через cancellation.
 */
final class LongCommandHandlerTest extends TestCase
{
    /**
     * Проверяет, что cancellation приводит к CancelledException (задача не “успешно завершается”).
     */
    public function testLongCommandCanBeCancelled(): void
    {
        $handler = new LongCommandHandler();
        $ctx = new TuiCommandContextDto(getcwd() ?: '.', new TuiHistoryDto());
        $input = new ParsedUserInputDto('/long 10', true, 'long', ['10']);

        $deferred = new DeferredCancellation();
        $cancellation = $deferred->getCancellation();

        // Запрашиваем отмену почти сразу — `delay(1, $cancellation)` должен отреагировать без ожидания 1 секунды.
        EventLoop::delay(0.01, static fn() => $deferred->cancel());

        $future = $handler->handleAsync($ctx, $input, $cancellation);

        $this->expectException(CancelledException::class);
        $future->await();
    }
}
