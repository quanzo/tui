<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCallDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\classes\tui\command\WorkspaceSubcommandRegistry;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Handler команды `/ws` (workspace).
 *
 * При создании читает классы из `src/handlers/workspace`, регистрирует их и вызывает
 * подкоманду по первому токену. Без токена выполняется `ls`. Хвост строки — параметры подкоманды.
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
    private WorkspaceSubcommandRegistry $subcommands;

    /**
     * @param WorkspaceSubcommandRegistry|null $subcommands Готовый реестр. Без аргумента читается каталог `workspace`.
     */
    public function __construct(?WorkspaceSubcommandRegistry $subcommands = null)
    {
        $this->subcommands = $subcommands ?? WorkspaceSubcommandRegistry::fromDirectory(__DIR__ . '/workspace');
    }

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
     * Возвращает справку `/ws` и строк подкоманд из их описаний.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto
    {
        $entries = [
            new TuiHelpRowDto('/ws', 'То же, что /ws ls: каталог текущего процесса'),
        ];
        foreach ($this->subcommands->toCatalog()->getItems() as $item) {
            $entries[] = new TuiHelpRowDto($item->getUsage(), $item->getDescription());
        }

        return new TuiCommandHelpDto($entries);
    }

    /**
     * Находит подкоманду и передаёт ей параметры хвоста строки.
     *
     * Неизвестное имя возвращает warning entry с текстом `Неизвестная подкоманда: {name}`.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @return TuiCommandResultDto
     */
    public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
    {
        $parsed = $this->parseInvocation($input);
        $subcommand = $this->subcommands->find($parsed->getName());
        if ($subcommand === null) {
            return $this->unknownSubcommand($parsed->getName());
        }

        return $subcommand->execute($ctx, $parsed->getParameters(), $this->subcommands->toCatalog());
    }

    /**
     * Отделяет имя подкоманды от параметров.
     *
     * Пустой список аргументов означает подкоманду `ls` без параметров.
     * Иначе первый токен — имя, остальные уже разобранные параметры (кавычки снимает парсер ввода).
     *
     * @param ParsedUserInputDto $input Разобранный ввод команды `/ws`
     * @return WorkspaceSubcommandCallDto
     */
    private function parseInvocation(ParsedUserInputDto $input): WorkspaceSubcommandCallDto
    {
        $args = $input->getArgs();
        if ($args === []) {
            return new WorkspaceSubcommandCallDto('ls', new WorkspaceSubcommandParametersDto([]));
        }

        $name = (string) $args[0];
        $tokens = [];
        $tail = array_slice($args, 1);
        foreach ($tail as $token) {
            $tokens[] = (string) $token;
        }

        return new WorkspaceSubcommandCallDto($name, new WorkspaceSubcommandParametersDto($tokens));
    }

    /**
     * Собирает предупреждение о неизвестной подкоманде.
     *
     * @param string $name Имя, которого нет в реестре
     * @return TuiCommandResultDto
     */
    private function unknownSubcommand(string $name): TuiCommandResultDto
    {
        $text = 'Неизвестная подкоманда: ' . $name;
        $entry = TuiHistoryEntryDto::output('ws unknown')
            ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Warning, $text)])
            ->setPlainText($text);

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }
}
