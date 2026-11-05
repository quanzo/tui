<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\ListBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/ws` (workspace).
 *
 * Подкоманды:
 * - `ls` — показать содержимое текущей директории (ограниченный список).
 *
 * Пример использования:
 *
 * ```php
 * $handler = new WorkspaceCommandHandler();
 * $result = $handler->handle($ctx, $input);
 * ```
 */
final class WorkspaceCommandHandler implements TuiCommandHandlerInterface
{
    /**
     * Возвращает имя команды (без префикса `/`).
     *
     * @return string
     */
    public function getName(): string
    {
        return 'ws';
    }

    /**
     * Обрабатывает workspace-команду.
     *
     * На текущем этапе поддерживается только подкоманда `ls`.
     * Неизвестные подкоманды возвращают warning entry.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $args = $input->getArgs();
        $sub = (string) ($args[0] ?? 'ls');

        if ($sub !== 'ls') {
            $entry = TuiHistoryEntryDto::output('ws unknown')
                ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Warning, 'Неизвестная подкоманда: ' . $sub)])
                ->setPlainText('Неизвестная подкоманда: ' . $sub);
            return (new TuiCommandResultDto())->setAppendEntries([$entry]);
        }

        $cwd = $ctx->getCwd();
        $rows = [];
        $items = @scandir($cwd) ?: [];
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $cwd . DIRECTORY_SEPARATOR . $name;
            $type = is_dir($path) ? 'dir' : 'file';
            $rows[] = [$name, $type];
            if (count($rows) >= 20) {
                break;
            }
        }

        $panel = new PanelBlockDto('Workspace', [
            new ListBlockDto(['Root: ' . $cwd]),
            new TableBlockDto(['Name', 'Type'], $rows),
        ]);

        $entry = TuiHistoryEntryDto::output('ws ls')
            ->setBlocks([$panel])
            ->setPlainText('workspace listed');

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }
}
