<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\async;

use Amp\Future;
use quanzo\tui\classes\dto\tui\async\ShellProcessResultDto;

/**
 * DTO “запущенного” shell-процесса.
 *
 * Нужен для интеграции с jobs:\n
 * - `future` завершается результатом;\n
 * - `forceCancel` позволяет best-effort принудительно завершить процесс (SIGTERM/SIGKILL),
 *   чтобы `/break` работал максимально “жёстко”, когда это возможно.
 */
final class ShellRunningProcessDto
{
    /**
     * @param Future<ShellProcessResultDto> $future
     * @param \Closure():void $forceCancel
     */
    public function __construct(
        private readonly Future $future,
        private readonly \Closure $forceCancel,
    ) {
    }

    /**
     * @return Future<ShellProcessResultDto>
     */
    public function getFuture(): Future
    {
        return $this->future;
    }

    /**
     * Best-effort принудительное прерывание процесса.
     *
     * @return void
     */
    public function forceCancel(): void
    {
        ($this->forceCancel)();
    }
}
