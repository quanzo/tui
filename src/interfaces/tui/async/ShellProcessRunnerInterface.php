<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\async;

use Amp\Cancellation;
use quanzo\tui\classes\tui\async\ShellRunningProcessDto;

/**
 * Контракт runner'а для shell-команд.
 *
 * Вынесен в интерфейс, чтобы:\n
 * - тестировать handler `/exec` без запуска реальных процессов;\n
 * - изолировать низкоуровневую работу с `proc_open()`.\n
 */
interface ShellProcessRunnerInterface
{
    /**
     * Стартует процесс и возвращает структуру с `Future` результата и `forceCancel`.\n
     *
     * @param string $command
     * @param Cancellation $cancellation
     * @return ShellRunningProcessDto
     */
    public function start(string $command, Cancellation $cancellation): ShellRunningProcessDto;
}
