<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\WorkspaceSubcommandParametersDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\tui\command\UserInputParser;
use quanzo\tui\classes\tui\command\WorkspaceSubcommandRegistry;
use quanzo\tui\handlers\WorkspaceCommandHandler;

/**
 * Разбор аргументов `/ws` и границы {@see WorkspaceSubcommandParametersDto}.
 */
final class WorkspaceCommandDispatchTest extends TestCase
{
    /**
     * Хвост после имени подкоманды доходит до неё токенами, включая кавычки, пустые и мусорные значения.
     *
     * Пустая сырая строка в провайдере означает, что аргументы заданы готовым списком, а не парсером.
     *
     * @param string $raw
     * @param list<string>|null $args
     * @param string $expected
     */
    #[DataProvider('dispatchCases')]
    public function testTailIsPassedToSubcommand(string $raw, ?array $args, string $expected): void
    {
        $registry = (new WorkspaceSubcommandRegistry())
            ->register(new WorkspaceSpySubcommand('ls'))
            ->register(new WorkspaceSpySubcommand('help'));
        $handler = new WorkspaceCommandHandler($registry);
        $input = $raw === ''
            ? new ParsedUserInputDto('/ws', true, 'ws', $args ?? [])
            : (new UserInputParser())->parse($raw);

        $result = $handler->handle(new TuiCommandContextDto('.', new TuiHistoryDto()), $input);

        $this->assertSame($expected, $result->getAppendEntries()[0]->getPlainText());
    }

    /**
     * @return list<array{0:string,1:list<string>|null,2:string}>
     */
    public static function dispatchCases(): array
    {
        return [
            ['/ws', null, '[]'],
            ['/ws ls', null, '[]'],
            ['/ws ls a b', null, '["a","b"]'],
            ['/ws ls "a b" --all', null, '["a b","--all"]'],
            ['/ws   ls   foo', null, '["foo"]'],
            ['/ws ls ""', null, '[""]'],
            ['/ws ls 0', null, '["0"]'],
            ['/ws ls -1', null, '["-1"]'],
            ['/ws ls abc', null, '["abc"]'],
            ['/ws nope', null, 'Неизвестная подкоманда: nope'],
            ['/ws LS', null, 'Неизвестная подкоманда: LS'],
            ["/ws ls 'x y'", null, '["x y"]'],
            ['/ws help extra', null, '["extra"]'],
            ['', [''], 'Неизвестная подкоманда: '],
            ['', ['   '], 'Неизвестная подкоманда:    '],
            ['', ['ls', '   '], '["   "]'],
        ];
    }

    /**
     * Чтение токена не выходит за список: отрицательный индекс и отсутствующий слот дают значение по умолчанию.
     *
     * @param list<string> $tokens
     * @param int $index
     * @param string $default
     * @param string $expected
     * @param int $count
     */
    #[DataProvider('parameterCases')]
    public function testParameterLookup(array $tokens, int $index, string $default, string $expected, int $count): void
    {
        $parameters = new WorkspaceSubcommandParametersDto($tokens);

        $this->assertSame($expected, $parameters->get($index, $default));
        $this->assertSame($count, $parameters->count());
    }

    /**
     * @return list<array{0:list<string>,1:int,2:string,3:string,4:int}>
     */
    public static function parameterCases(): array
    {
        return [
            [[], 0, 'fallback', 'fallback', 0],
            [[], -1, 'fallback', 'fallback', 0],
            [['a'], 0, 'fallback', 'a', 1],
            [['a'], 1, 'fallback', 'fallback', 1],
            [['a', ''], 1, 'fallback', '', 2],
            [['0'], 0, 'fallback', '0', 1],
            [['-1'], 0, 'fallback', '-1', 1],
            [['   '], 0, 'fallback', '   ', 1],
            [['a b', '--all'], 0, 'fallback', 'a b', 2],
            [['abc'], -5, '', '', 1],
            [['x'], 99, 'no', 'no', 1],
        ];
    }

    /**
     * Изменение исходного массива и возвращённой копии не меняет токены внутри DTO.
     */
    public function testParameterTokensAreCopied(): void
    {
        $source = ['a'];
        $parameters = new WorkspaceSubcommandParametersDto($source);
        $source[0] = 'mutated-source';
        $read = $parameters->getTokens();
        $read[0] = 'mutated-copy';

        $this->assertSame('a', $parameters->get(0));
        $this->assertSame(['a'], $parameters->getTokens());
    }
}
