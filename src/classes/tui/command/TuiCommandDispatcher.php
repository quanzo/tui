<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\command;

use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandDispatchResultDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\interfaces\tui\command\AsyncTuiCommandHandlerInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandRegistryInterface;
use quanzo\tui\interfaces\tui\command\TuiNamedCommandHandlerInterface;

/**
 * Диспетчер TUI-команд: выбирает handler по имени.
 *
 * Пример использования:
 *
 * ```php
 * $dispatcher = new TuiCommandDispatcher([$helpHandler]);
 * $res = $dispatcher->dispatch($ctx, $input);
 * ```
 */
final class TuiCommandDispatcher implements TuiCommandRegistryInterface
{
    /** @var array<string, TuiNamedCommandHandlerInterface> */
    private array $handlersByName = [];

    /**
     * @param list<TuiNamedCommandHandlerInterface> $handlers
     */
    public function __construct(array $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlersByName[$handler->getName()] = $handler;
        }
    }

    /**
     * Регистрирует handler в диспетчере.
     *
     * Если handler с таким именем уже был зарегистрирован, он будет перезаписан (overwrite).
     *
     * @param TuiNamedCommandHandlerInterface $handler
     * @return self
     */
    public function addHandler(TuiNamedCommandHandlerInterface $handler): self
    {
        $this->handlersByName[$handler->getName()] = $handler;
        return $this;
    }

    /**
     * Удаляет handler по имени команды.
     *
     * @param string $name Имя команды (без `/`)
     * @return bool true если handler был найден и удалён, иначе false
     */
    public function removeHandlerByName(string $name): bool
    {
        if (!array_key_exists($name, $this->handlersByName)) {
            return false;
        }

        unset($this->handlersByName[$name]);
        return true;
    }

    /**
     * Удаляет handler по экземпляру.
     *
     * Удаление выполняется по имени handler'а (`$handler->getName()`).
     *
     * @param TuiNamedCommandHandlerInterface $handler
     * @return bool true если handler был найден и удалён, иначе false
     */
    public function removeHandler(TuiNamedCommandHandlerInterface $handler): bool
    {
        return $this->removeHandlerByName($handler->getName());
    }

    /**
     * Возвращает handlers в порядке первой регистрации.
     *
     * Повторная регистрация того же имени заменяет экземпляр и не сдвигает позицию.
     * В списке те же объекты, что хранит диспетчер.
     *
     * @return list<TuiNamedCommandHandlerInterface>
     */
    public function listHandlers(): array
    {
        return array_values($this->handlersByName);
    }

    /**
     * Диспетчеризует команду на handler по имени и возвращает результат диспетчеризации.
     *
     * Поведение:
     * - если handler не найден — `isHandled()` будет false;
     * - если handler sync — возвращается `sync(result, handler)`;
     * - если handler async — возвращается `async(handler)`, а вызыватель решает, как/где запускать Future.
     *
     * @param TuiCommandContextDto $ctx Контекст выполнения (cwd и т.д.)
     * @param ParsedUserInputDto $input Результат парсинга пользовательского ввода
     * @return TuiCommandDispatchResultDto DTO диспетчеризации (sync/async/not handled)
     */
    public function dispatch(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandDispatchResultDto
    {
        $name = (string) $input->getCommandName();
        $handler = $this->handlersByName[$name] ?? null;
        if ($handler === null) {
            return new TuiCommandDispatchResultDto();
        }

        if ($handler instanceof AsyncTuiCommandHandlerInterface) {
            return TuiCommandDispatchResultDto::async($handler);
        }

        if ($handler instanceof TuiCommandHandlerInterface) {
            return TuiCommandDispatchResultDto::sync($handler->handle($ctx, $input), $handler);
        }

        // Теоретически невозможно: реестр должен содержать только совместимые handlers.
        return new TuiCommandDispatchResultDto();
    }
}
