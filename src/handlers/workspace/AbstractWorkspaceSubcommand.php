<?php

declare(strict_types=1);

namespace quanzo\tui\handlers\workspace;

use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCatalogDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\WorkspaceSubcommandInterface;
use quanzo\tui\interfaces\tui\view\TuiBlockInterface;

/**
 * Общий предок подкоманд `/ws`.
 *
 * Имя и описание задаёт наследник. Здесь собраны строка вызова и сборка результата в историю.
 *
 * Пример использования:
 *
 * ```php
 * final class LsSubcommand extends AbstractWorkspaceSubcommand
 * {
 *     public function getName(): string { return 'ls'; }
 *     public function getDescription(): string { return 'Показать каталог'; }
 *     public function execute(
 *         TuiCommandContextDto $ctx,
 *         WorkspaceSubcommandParametersDto $parameters,
 *         WorkspaceSubcommandCatalogDto $catalog,
 *     ): TuiCommandResultDto {
 *         return $this->output('ws ls', 'workspace listed', []);
 *     }
 * }
 * ```
 */
abstract class AbstractWorkspaceSubcommand implements WorkspaceSubcommandInterface
{
    /**
     * Возвращает имя подкоманды без префикса `/ws`.
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Возвращает описание подкоманды для справки.
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Выполняет подкоманду.
     *
     * @param TuiCommandContextDto $ctx Контекст TUI: рабочий каталог и история
     * @param WorkspaceSubcommandParametersDto $parameters Токены после имени подкоманды
     * @param WorkspaceSubcommandCatalogDto $catalog Зарегистрированные подкоманды
     * @return TuiCommandResultDto
     */
    abstract public function execute(
        TuiCommandContextDto $ctx,
        WorkspaceSubcommandParametersDto $parameters,
        WorkspaceSubcommandCatalogDto $catalog,
    ): TuiCommandResultDto;

    /**
     * Возвращает строку вызова подкоманды для таблицы справки.
     *
     * @return string
     */
    public function getUsage(): string
    {
        return '/ws ' . $this->getName();
    }

    /**
     * Собирает успешный результат с блоками вывода.
     *
     * @param string $source Метка записи истории, например `ws ls`
     * @param string $plain Плоский текст записи
     * @param list<TuiBlockInterface> $blocks Блоки, которые рисует форматтер
     * @return TuiCommandResultDto
     */
    protected function output(string $source, string $plain, array $blocks): TuiCommandResultDto
    {
        $entry = TuiHistoryEntryDto::output($source)
            ->setBlocks(array_values($blocks))
            ->setPlainText($plain);

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }

    /**
     * Собирает предупреждение, не прерывая TUI.
     *
     * @param string $source Метка записи истории
     * @param string $text Текст предупреждения
     * @return TuiCommandResultDto
     */
    protected function warning(string $source, string $text): TuiCommandResultDto
    {
        $entry = TuiHistoryEntryDto::output($source)
            ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Warning, $text)])
            ->setPlainText($text);

        return (new TuiCommandResultDto())->setAppendEntries([$entry]);
    }
}
