<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/exit`.
 *
 * Команда устанавливает флаг `exit=true` в результате, чтобы TUI завершила основной цикл.
 */
final class ExitCommandHandler implements TuiCommandHandlerInterface
{
    /**
     * Возвращает имя команды (без префикса `/`).
     *
     * @return string
     */
    public function getName(): string
    {
        return 'exit';
    }

    /**
     * Запрашивает завершение работы TUI.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $entry = TuiHistoryEntryDto::event('exit')
            ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Выход...')])
            ->setPlainText('Выход...');

        return (new TuiCommandResultDto())
            ->setExit(true)
            ->setAppendEntries([$entry]);
    }
}
