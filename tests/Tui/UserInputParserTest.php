<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\tui\command\UserInputParser;

/**
 * Тесты {@see UserInputParser}: команды, кавычки, мусор и битый UTF-8.
 */
final class UserInputParserTest extends TestCase
{
    /**
     * Разбор строки: пустой ввод, не-команда, кавычки и заведомо кривые данные.
     *
     * @param string $raw
     * @param bool $isCommand
     * @param string|null $commandName
     * @param list<string> $args
     */
    #[DataProvider('parseCases')]
    public function testParse(string $raw, bool $isCommand, ?string $commandName, array $args): void
    {
        $dto = (new UserInputParser())->parse($raw);

        $this->assertSame($raw, $dto->getRaw());
        $this->assertSame($isCommand, $dto->isCommand());
        $this->assertSame($commandName, $dto->getCommandName());
        $this->assertSame($args, $dto->getArgs());
    }

    /**
     * Набор из граничных и неправильных строк. Сырой текст в DTO не переписывается.
     *
     * @return list<array{0:string,1:bool,2:?string,3:list<string>}>
     */
    public static function parseCases(): array
    {
        return [
            ['', false, null, []],
            ['   ', false, null, []],
            ["\n\t", false, null, []],
            ['hello', false, null, []],
            ['  not a command  ', false, null, []],
            ['help', false, null, []],
            ['/', true, null, []],
            ['/   ', true, null, []],
            ['  /help  ', true, 'help', []],
            ['/ws ls', true, 'ws', ['ls']],
            ['/ws add "path with spaces"', true, 'ws', ['add', 'path with spaces']],
            ["/ws add 'a b'", true, 'ws', ['add', 'a b']],
            ['/exec echo ""', true, 'exec', ['echo', '']],
            ["/exec echo '0'", true, 'exec', ['echo', '0']],
            ['/say "hello', true, 'say', ['"hello']],
            ["/help\nextra", true, 'help', ['extra']],
            ['/exec echo "a\\"b"', true, 'exec', ['echo', 'a"b']],
            ["/\xFF", true, "\xFF", []],
            ["/\xFFmore", true, "\xFFmore", []],
        ];
    }
}
