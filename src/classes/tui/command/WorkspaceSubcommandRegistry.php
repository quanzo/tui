<?php

declare(strict_types=1);

namespace quanzo\tui\classes\tui\command;

use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandCatalogDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandDescriptorDto;
use quanzo\tui\handlers\workspace\AbstractWorkspaceSubcommand;

/**
 * Реестр подкоманд `/ws`.
 *
 * Читает PHP-классы из каталога подкоманд, регистрирует их по имени и отдаёт каталог для справки.
 *
 * Пример использования:
 *
 * ```php
 * $registry = WorkspaceSubcommandRegistry::fromDirectory(__DIR__ . '/../../../handlers/workspace');
 * $ls = $registry->find('ls');
 * ```
 */
final class WorkspaceSubcommandRegistry
{
    private const SUBCOMMAND_NAMESPACE = 'quanzo\\tui\\handlers\\workspace\\';

    /** @var array<string, AbstractWorkspaceSubcommand> */
    private array $byName = [];

    /**
     * Регистрирует подкоманду.
     *
     * Повтор того же имени — ошибка сборки реестра, а не тихая замена.
     *
     * @param AbstractWorkspaceSubcommand $subcommand Готовый экземпляр без обязательных аргументов конструктора
     * @return self
     */
    public function register(AbstractWorkspaceSubcommand $subcommand): self
    {
        $name = $subcommand->getName();
        if (isset($this->byName[$name])) {
            throw new \LogicException('Подкоманда уже зарегистрирована: ' . $name);
        }

        $this->byName[$name] = $subcommand;

        return $this;
    }

    /**
     * Ищет подкоманду по имени.
     *
     * @param string $name Имя без префикса `/ws`. Пустая строка не совпадает ни с одной командой.
     * @return AbstractWorkspaceSubcommand|null
     */
    public function find(string $name): ?AbstractWorkspaceSubcommand
    {
        return $this->byName[$name] ?? null;
    }

    /**
     * Собирает каталог описаний, отсортированный по имени.
     *
     * @return WorkspaceSubcommandCatalogDto
     */
    public function toCatalog(): WorkspaceSubcommandCatalogDto
    {
        $items = [];
        foreach ($this->byName as $subcommand) {
            $items[] = new WorkspaceSubcommandDescriptorDto(
                $subcommand->getName(),
                $subcommand->getDescription(),
                $subcommand->getUsage(),
            );
        }

        usort(
            $items,
            static fn (WorkspaceSubcommandDescriptorDto $left, WorkspaceSubcommandDescriptorDto $right): int =>
                $left->getName() <=> $right->getName(),
        );

        return new WorkspaceSubcommandCatalogDto($items);
    }

    /**
     * Читает каталог с классами подкоманд и регистрирует подходящие.
     *
     * Берёт `*.php`, кроме абстрактного предка. Имя класса — namespace `quanzo\tui\handlers\workspace`
     * плюс имя файла. Класс должен наследовать `AbstractWorkspaceSubcommand` и создаваться без аргументов.
     * Чужие файлы пропускаются. Повтор имени выбрасывает `LogicException`.
     *
     * @param string $directory Абсолютный или относительный путь к каталогу подкоманд
     * @return self
     */
    public static function fromDirectory(string $directory): self
    {
        $registry = new self();
        if (!is_dir($directory)) {
            return $registry;
        }

        $files = scandir($directory);
        if ($files === false) {
            return $registry;
        }

        sort($files);
        foreach ($files as $file) {
            $subcommand = self::instantiateFile($file);
            if ($subcommand === null) {
                continue;
            }
            $registry->register($subcommand);
        }

        return $registry;
    }

    /**
     * Создаёт подкоманду по имени файла, если файл описывает подходящий класс.
     *
     * @param string $file Имя файла внутри каталога, не путь
     * @return AbstractWorkspaceSubcommand|null
     */
    private static function instantiateFile(string $file): ?AbstractWorkspaceSubcommand
    {
        if (!str_ends_with($file, '.php')) {
            return null;
        }

        $base = substr($file, 0, -4);
        if ($base === '' || $base === 'AbstractWorkspaceSubcommand') {
            return null;
        }

        $class = self::SUBCOMMAND_NAMESPACE . $base;
        if (!class_exists($class)) {
            return null;
        }

        $reflection = new \ReflectionClass($class);
        if ($reflection->isAbstract() || !$reflection->isSubclassOf(AbstractWorkspaceSubcommand::class)) {
            return null;
        }
        if (!$reflection->isInstantiable()) {
            return null;
        }

        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        $instance = $reflection->newInstance();
        if (!$instance instanceof AbstractWorkspaceSubcommand) {
            return null;
        }

        return $instance;
    }
}
