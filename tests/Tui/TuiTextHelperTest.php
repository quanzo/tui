<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\helpers\TuiTextHelper;

/**
 * Тесты {@see TuiTextHelper}: ширина, перенос, ANSI и заведомо плохие лимиты.
 */
final class TuiTextHelperTest extends TestCase
{
    /**
     * Дополнение пробелами не трогает строку, которая уже шире лимита, включая отрицательную ширину.
     *
     * @param string $input
     * @param int $width
     * @param string $expected
     */
    #[DataProvider('padCases')]
    public function testPadString(string $input, int $width, string $expected): void
    {
        $this->assertSame($expected, TuiTextHelper::padString($input, $width));
    }

    /**
     * @return list<array{0:string,1:int,2:string}>
     */
    public static function padCases(): array
    {
        return [
            ['ok', 10, 'ok        '],
            ['', 3, '   '],
            ['abcd', 2, 'abcd'],
            ['привет', 3, 'привет'],
            ['a', 0, 'a'],
            ['a', -5, 'a'],
            ["\033[31mab\033[0m", 4, "\033[31mab\033[0m  "],
            [' ', 1, ' '],
            ['я', 2, 'я '],
            ['0', 1, '0'],
        ];
    }

    /**
     * Перенос по колонке: нулевая ширина, пустая строка и символ шире лимита.
     *
     * @param string $line
     * @param int $width
     * @param list<string> $expected
     */
    #[DataProvider('splitCases')]
    public function testSplitByWidth(string $line, int $width, array $expected): void
    {
        $this->assertSame($expected, TuiTextHelper::splitByWidth($line, $width));
    }

    /**
     * @return list<array{0:string,1:int,2:list<string>}>
     */
    public static function splitCases(): array
    {
        return [
            ['abcd', 2, ['ab', 'cd']],
            ['abc', 2, ['ab', 'c']],
            ['', 5, []],
            ['abc', 0, ['abc']],
            ['abc', -3, ['abc']],
            ['яя', 1, ['я', 'я']],
            ['你你', 1, ['你', '你']],
            ['a', 10, ['a']],
            ["a\tb", 1, ['a', "\t", 'b']],
            ['   ', 2, ['  ', ' ']],
        ];
    }

    /**
     * Видимая ширина игнорирует ANSI и считает кириллицу по колонкам, а не по байтам.
     */
    public function testVisibleWidthAndStripAnsi(): void
    {
        $colored = "\033[31mred\033[0m";
        $this->assertSame('red', TuiTextHelper::stripAnsi($colored));
        $this->assertSame(3, TuiTextHelper::visibleWidth($colored));
        $this->assertSame(0, TuiTextHelper::visibleWidth(''));
        $this->assertSame(6, TuiTextHelper::visibleWidth('привет'));
        $this->assertSame(2, TuiTextHelper::visibleWidth('你'));
        $this->assertSame(0, TuiTextHelper::visibleWidth("\033[0m"));
    }

    /**
     * Обрезка с многоточием: лимит 0, строка короче лимита и многоточие шире лимита.
     *
     * @param string $input
     * @param int $width
     * @param string $ellipsis
     * @param string $expected
     */
    #[DataProvider('trimCases')]
    public function testTrimToWidthWithEllipsis(string $input, int $width, string $ellipsis, string $expected): void
    {
        $this->assertSame($expected, TuiTextHelper::trimToWidthWithEllipsis($input, $width, $ellipsis));
    }

    /**
     * @return list<array{0:string,1:int,2:string,3:string}>
     */
    public static function trimCases(): array
    {
        return [
            ['hello', 4, '…', 'hel…'],
            ['hi', 10, '…', 'hi'],
            ['hello', 0, '…', ''],
            ['hello', -2, '…', ''],
            ['', 3, '…', ''],
            ['привет', 4, '…', 'при…'],
            ['hello', 1, '...', '...'],
            ["\033[31mhello\033[0m", 4, '…', 'hel…'],
            ['ab', 2, '…', 'ab'],
            ['abcd', 1, '…', '…'],
            ['x', 1, '', 'x'],
        ];
    }

    /**
     * ANSI-обрезка не рвёт escape и сбрасывает цвет, если последовательность уже началась.
     */
    public function testTrimAnsiToVisibleWidth(): void
    {
        $colored = "\033[31mhello\033[0m";
        $cut = TuiTextHelper::trimAnsiToVisibleWidth($colored, 2);
        $this->assertSame(2, TuiTextHelper::visibleWidth($cut));
        $this->assertStringContainsString("\033[31m", $cut);
        $this->assertStringEndsWith("\033[0m", $cut);

        $this->assertSame('', TuiTextHelper::trimAnsiToVisibleWidth('hello', 0));
        $this->assertSame('', TuiTextHelper::trimAnsiToVisibleWidth('hello', -1));
        $this->assertSame('hi', TuiTextHelper::trimAnsiToVisibleWidth('hi', 10));
        $this->assertSame('h', TuiTextHelper::trimAnsiToVisibleWidth('h', 1));
    }

    /**
     * История в плоские строки: перенос, пустой список и хвостовой пустой разделитель снимается.
     */
    public function testBuildDisplayLines(): void
    {
        $this->assertSame([], TuiTextHelper::buildDisplayLines([], 10));
        $this->assertSame(['ab', '', 'cd', 'ef'], TuiTextHelper::buildDisplayLines(['ab', 'cdef'], 2));
        $this->assertSame(['a', 'b'], TuiTextHelper::buildDisplayLines(["a\nb"], 10));
        $this->assertSame(['long'], TuiTextHelper::buildDisplayLines(['long'], 0));
        $this->assertSame([], TuiTextHelper::buildDisplayLines([''], 5));
    }
}
