<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\async;

/**
 * DTO результата выполнения shell-команды.
 *
 * Этот DTO предназначен для использования в async-пайплайне `/exec`:
 * runner собирает stdout/stderr и код возврата, затем handler преобразует результат в entries истории.
 */
final class ShellProcessResultDto
{
    public function __construct(
        private readonly string $command,
        private readonly int $exitCode,
        private readonly string $stdout,
        private readonly string $stderr,
        private readonly bool $wasTruncated,
    ) {
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function wasTruncated(): bool
    {
        return $this->wasTruncated;
    }
}
