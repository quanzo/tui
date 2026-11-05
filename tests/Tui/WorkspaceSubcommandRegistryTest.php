<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\tui\command\WorkspaceSubcommandRegistry;

/**
 * Чтение каталога подкоманд `/ws` и поиск по имени.
 */
final class WorkspaceSubcommandRegistryTest extends TestCase
{
    private const FIXTURE_DIR_NAME = 'tui-ws-reg-fixture';

    /**
     * Удаляет временный каталог, который провайдер собирает для пустого реестра.
     */
    public static function tearDownAfterClass(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::FIXTURE_DIR_NAME;
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }

    /**
     * Боевой каталог `src/handlers/workspace` регистрирует `help` и `ls` с непустыми описаниями.
     */
    public function testRealDirectoryRegistersHelpAndLs(): void
    {
        $registry = WorkspaceSubcommandRegistry::fromDirectory(dirname(__DIR__, 2) . '/src/handlers/workspace');
        $items = $registry->toCatalog()->getItems();

        $this->assertCount(2, $items);
        $this->assertSame('help', $items[0]->getName());
        $this->assertSame('/ws help', $items[0]->getUsage());
        $this->assertSame('Справка по подкомандам workspace', $items[0]->getDescription());
        $this->assertSame('ls', $items[1]->getName());
        $this->assertSame('/ws ls', $items[1]->getUsage());
        $this->assertSame('Показать содержимое текущей директории', $items[1]->getDescription());
        $this->assertInstanceOf(\quanzo\tui\handlers\workspace\LsSubcommand::class, $registry->find('ls'));
        $this->assertInstanceOf(\quanzo\tui\handlers\workspace\HelpSubcommand::class, $registry->find('help'));
        $this->assertNull($registry->find('missing'));
    }

    /**
     * Каталог без классов подкоманд, отсутствующий путь и путь к файлу дают пустой реестр.
     *
     * @param string $directory
     */
    #[DataProvider('emptyDirectoryCases')]
    public function testUnusableDirectoryIsEmpty(string $directory): void
    {
        $registry = WorkspaceSubcommandRegistry::fromDirectory($directory);

        $this->assertSame([], $registry->toCatalog()->getItems());
        $this->assertNull($registry->find('ls'));
        $this->assertNull($registry->find(''));
    }

    /**
     * @return list<array{0:string}>
     */
    public static function emptyDirectoryCases(): array
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::FIXTURE_DIR_NAME;
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        file_put_contents($dir . '/notes.txt', 'x');
        file_put_contents($dir . '/MissingSubcommand.php', "<?php\n// not a workspace subcommand\n");
        file_put_contents($dir . '/AbstractWorkspaceSubcommand.php', "<?php\n// skipped by name\n");

        return [
            [$dir],
            [$dir . '/notes.txt'],
            [$dir . '/does-not-exist'],
            ['/no/such/tui-ws-registry'],
            [''],
            [$dir . '/MissingSubcommand.php'],
            [$dir . '/AbstractWorkspaceSubcommand.php'],
            [$dir . '/nested/missing'],
            ['/no/such/tui-ws-registry/ls'],
            [$dir . '/notes.txt/extra'],
        ];
    }

    /**
     * Поиск отличает точное имя от пустой строки, другого регистра и мусора.
     *
     * @param string $name
     * @param string|null $expected
     */
    #[DataProvider('findCases')]
    public function testFindByName(string $name, ?string $expected): void
    {
        $registry = (new WorkspaceSubcommandRegistry())
            ->register(new WorkspaceSpySubcommand('ls'))
            ->register(new WorkspaceSpySubcommand('help'));

        $found = $registry->find($name);

        $this->assertSame($expected, $found?->getName());
    }

    /**
     * @return list<array{0:string,1:string|null}>
     */
    public static function findCases(): array
    {
        return [
            ['ls', 'ls'],
            ['help', 'help'],
            ['', null],
            ['LS', null],
            ['nope', null],
            ['ls ', null],
            ['/ws ls', null],
            ['0', null],
            ['-1', null],
            ['help/ls', null],
        ];
    }

    /**
     * Повторное имя не затирает первую подкоманду.
     */
    public function testDuplicateNameThrows(): void
    {
        $registry = (new WorkspaceSubcommandRegistry())
            ->register(new WorkspaceSpySubcommand('ls'));

        $this->expectException(\LogicException::class);
        $registry->register(new WorkspaceSpySubcommand('ls'));
    }

    /**
     * Каталог сортирует имена и не отдаёт те же объекты дескрипторов наружу.
     */
    public function testCatalogIsSortedAndCopied(): void
    {
        $registry = (new WorkspaceSubcommandRegistry())
            ->register(new WorkspaceSpySubcommand('ls'))
            ->register(new WorkspaceSpySubcommand('help'));

        $first = $registry->toCatalog()->getItems();
        $second = $registry->toCatalog()->getItems();

        $this->assertSame(['help', 'ls'], [$first[0]->getName(), $first[1]->getName()]);
        $this->assertNotSame($first[0], $second[0]);
        $this->assertSame($first[0]->getDescription(), $second[0]->getDescription());
    }
}
