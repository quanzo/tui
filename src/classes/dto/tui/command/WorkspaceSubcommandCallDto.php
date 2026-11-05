<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\command;

/**
 * Разобранный вызов подкоманды `/ws`: имя и параметры хвоста.
 *
 * Пример использования:
 *
 * ```php
 * $call = new WorkspaceSubcommandCallDto('ls', new WorkspaceSubcommandParametersDto(['src']));
 * ```
 */
final class WorkspaceSubcommandCallDto
{
    /**
     * @param string $name Имя подкоманды. Пустая строка допустима и не совпадёт с реестром.
     * @param WorkspaceSubcommandParametersDto $parameters Токены после имени
     */
    public function __construct(
        private readonly string $name,
        private readonly WorkspaceSubcommandParametersDto $parameters,
    ) {
    }

    /**
     * Возвращает имя подкоманды.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Возвращает параметры вызова.
     *
     * Это тот же объект, который собран для этого вызова. Токены внутри DTO копируются при чтении.
     *
     * @return WorkspaceSubcommandParametersDto
     */
    public function getParameters(): WorkspaceSubcommandParametersDto
    {
        return new WorkspaceSubcommandParametersDto($this->parameters->getTokens());
    }
}
