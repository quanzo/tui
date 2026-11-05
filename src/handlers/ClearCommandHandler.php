<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/clear`.
 *
 * Команда очищает историю (output) и возвращает информационное сообщение.
 */
final class ClearCommandHandler implements TuiCommandHandlerInterface
{
    /**
     * Возвращает имя команды (без префикса `/`).
     *
     * @return string
     */
    public function getName(): string
    {
        return 'clear';
    }

    /**
     * Возвращает справку команды `/clear`.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto
    {
        return new TuiCommandHelpDto([
            new TuiHelpRowDto('/clear', 'Очистить историю'),
        ]);
    }

    /**
     * Запрашивает очистку истории.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $entry = TuiHistoryEntryDto::event('history cleared')
            ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'История очищена')])
            ->setPlainText('История очищена');

        return (new TuiCommandResultDto())
            ->setClearHistory(true)
            ->setAppendEntries([$entry]);
    }
}
