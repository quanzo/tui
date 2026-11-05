<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\command;

/**
 * Краткое описание подкоманды `/ws` для справки.
 *
 * Пример использования:
 *
 * ```php
 * $item = new WorkspaceSubcommandDescriptorDto('ls', 'Показать каталог', '/ws ls');
 * ```
 */
final class WorkspaceSubcommandDescriptorDto
{
    /**
     * @param string $name Имя подкоманды без `/ws`
     * @param string $description Текст, который подкоманда сообщает о себе
     * @param string $usage Строка вызова, например `/ws ls`
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly string $usage,
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
     * Возвращает описание подкоманды.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Возвращает строку вызова для таблицы справки.
     *
     * @return string
     */
    public function getUsage(): string
    {
        return $this->usage;
    }
}
