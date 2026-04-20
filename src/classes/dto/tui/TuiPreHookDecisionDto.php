<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui;

use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;

/**
 * DTO решения pre-hook для интерактивного TUI.
 *
 * DTO является результатом работы {@see \quanzo\tui\interfaces\tui\TuiPreOutputHookInterface::decide()}.
 *
 * Роль в архитектуре:
 * - `InteractiveCommand` получает введённый пользователем текст (submit по Enter) как `submittedInput`;\n
 * - затем вызывает pre-hook `decide($submittedInput)`;\n
 * - pre-hook возвращает экземпляр этого DTO;\n
 * - `InteractiveCommand` применяет решение:\n
 *   - при необходимости очищает историю;\n
 *   - добавляет entries в rich-историю;\n
 *   - при необходимости завершает TUI-цикл.\n
 *
 * Что содержит решение:
 * - **appendEntries**: список {@see TuiHistoryEntryDto}, которые будут добавлены в {@see \quanzo\tui\classes\dto\tui\history\TuiHistoryDto}.\n
 * - **clearHistory**: флаг “очистить историю перед добавлением новых entries”.\n
 * - **exit**: флаг “завершить работу TUI после применения решения”.\n
 *
 * Важно:
 * - DTO не исполняет команду и не делает IO; это просто “команда для UI”.\n
 * - DTO хранит `originalInput` (текст submit), чтобы вызывающая сторона могла отобразить/логировать исходный ввод.\n
 * - `appendEntries` — это **структурированный** вывод (Variant C): blocks внутри entries уже готовы для форматтера/рендера.\n
 *
 * Пример использования:
 *
 * ```php
 * $decision = new TuiPreHookDecisionDto($originalInput);
 *
 * // (опционально) очистить историю
 * $decision->setClearHistory(true);
 *
 * // добавить новые entries в вывод
 * $decision->setAppendEntries([
 *     TuiHistoryEntryDto::output('ok')->setPlainText('OK'),
 * ]);
 *
 * // (опционально) завершить TUI
 * $decision->setExit(false);
 * ```
 */
final class TuiPreHookDecisionDto
{
    /** @var list<TuiHistoryEntryDto> */
    private array $appendEntries = [];

    /**
     * Если true, то история будет очищена перед добавлением новых entries.
     *
     * Типичный сценарий: команда `/clear`, которая очищает вывод.
     */
    private bool $clearHistory = false;

    /**
     * Если true, то TUI должна завершиться после применения решения.
     *
     * Типичный сценарий: команда `/exit`.
     */
    private bool $exit = false;

    /**
     * @param string $originalInput Исходный текст, который был отправлен (submit) пользователем.
     */
    public function __construct(
        private readonly string $originalInput,
    ) {
    }

    /**
     * Возвращает исходный пользовательский ввод, переданный в pre-hook.
     *
     * Это значение удобно использовать для:\n
     * - построения сообщений/истории;\n
     * - логирования;\n
     * - отладки поведения парсера.\n
     *
     * @return string
     */
    public function getOriginalInput(): string
    {
        return $this->originalInput;
    }

    /**
     * Возвращает entries, которые нужно добавить в историю.
     *
     * Важно: entries должны быть полностью подготовлены (blocks/meta/plainText),
     * чтобы рендер/форматтер могли корректно их показать.
     *
     * @return list<TuiHistoryEntryDto>
     */
    public function getAppendEntries(): array
    {
        return $this->appendEntries;
    }

    /**
     * Устанавливает entries, которые нужно добавить в историю.
     *
     * Метод нормализует вход как list (сбрасывает ключи).
     *
     * @param list<TuiHistoryEntryDto> $appendEntries
     * @return self
     */
    public function setAppendEntries(array $appendEntries): self
    {
        $this->appendEntries = array_values($appendEntries);
        return $this;
    }

    /**
     * Флаг “очистить историю”.
     *
     * Если true, `InteractiveCommand` сначала очищает историю, а затем добавляет entries.
     *
     * @return bool
     */
    public function isClearHistory(): bool
    {
        return $this->clearHistory;
    }

    /**
     * Устанавливает флаг “очистить историю”.
     *
     * @param bool $clearHistory
     * @return self
     */
    public function setClearHistory(bool $clearHistory): self
    {
        $this->clearHistory = $clearHistory;
        return $this;
    }

    /**
     * Флаг “выйти из TUI”.
     *
     * Если true, `InteractiveCommand` завершает цикл (после применения entries/очистки).
     *
     * @return bool
     */
    public function isExit(): bool
    {
        return $this->exit;
    }

    /**
     * Устанавливает флаг “выйти из TUI”.
     *
     * @param bool $exit
     * @return self
     */
    public function setExit(bool $exit): self
    {
        $this->exit = $exit;
        return $this;
    }
}
