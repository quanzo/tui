<?php

declare(strict_types=1);

namespace quanzo\tui\interfaces\tui\command;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;

/**
 * Контракт обработчика пользовательских команд TUI.
 *
 * Пример использования:
 *
 * ```php
 * final class HelpHandler implements TuiCommandHandlerInterface { ... }
 * ```
 */
interface TuiCommandHandlerInterface
{
    public function getName(): string;

    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto;
}
