<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\helpers\TuiInputBufferHelper;

/**
 * Тесты {@see TuiInputBufferHelper}: границы курсора, UTF-8 и многострочный буфер.
 */
final class TuiInputBufferHelperTest extends TestCase
{
    /**
     * Смещение за пределами строки прижимается к [0, длина].
     *
     * @param string $buffer
     * @param int $offset
     * @param int $expected
     */
    #[DataProvider('clampCases')]
    public function testClampOffset(string $buffer, int $offset, int $expected): void
    {
        $this->assertSame($expected, TuiInputBufferHelper::clampOffset($buffer, $offset));
    }

    /**
     * @return list<array{0:string,1:int,2:int}>
     */
    public static function clampCases(): array
    {
        return [
            ['', 0, 0],
            ['', -1, 0],
            ['', 5, 0],
            ['ab', -10, 0],
            ['ab', 0, 0],
            ['ab', 1, 1],
            ['ab', 2, 2],
            ['ab', 99, 2],
            ['я', 1, 1],
            ['я', 2, 1],
            ["a\nb", -1, 0],
        ];
    }

    /**
     * Вставка: пустой текст, смещение за концом и символ UTF-8.
     */
    public function testInsert(): void
    {
        $this->assertSame(['ab', 1], TuiInputBufferHelper::insert('ab', 1, ''));
        $this->assertSame(['abc', 3], TuiInputBufferHelper::insert('ab', 99, 'c'));
        $this->assertSame(['бя', 1], TuiInputBufferHelper::insert('я', -5, 'б'));
        $this->assertSame(["a\nb", 2], TuiInputBufferHelper::insert('ab', 1, "\n"));
        $this->assertSame(['', 0], TuiInputBufferHelper::insert('', 0, ''));
    }

    /**
     * Backspace и Delete на пустом буфере, в начале и в конце ничего не ломают.
     */
    public function testBackspaceAndDeleteAtEdges(): void
    {
        $this->assertSame(['', 0], TuiInputBufferHelper::backspace('', 0));
        $this->assertSame(['a', 0], TuiInputBufferHelper::backspace('a', 0));
        $this->assertSame(['б', 0], TuiInputBufferHelper::backspace('яб', 1));
        $this->assertSame(['a', 1], TuiInputBufferHelper::delete('a', 1));
        $this->assertSame(['a', 1], TuiInputBufferHelper::delete('a', 5));
        $this->assertSame(['ав', 1], TuiInputBufferHelper::delete('абв', 1));
        $this->assertSame(['', 0], TuiInputBufferHelper::delete('', -1));
    }

    /**
     * Курсор в координатах строки и обратное преобразование держат границы.
     *
     * @param string $buffer
     * @param int $offset
     * @param int $line
     * @param int $col
     */
    #[DataProvider('lineColCases')]
    public function testCursorLineColRoundTrip(string $buffer, int $offset, int $line, int $col): void
    {
        $this->assertSame([$line, $col], TuiInputBufferHelper::cursorLineCol($buffer, $offset));
        $this->assertSame(
            TuiInputBufferHelper::clampOffset($buffer, $offset),
            TuiInputBufferHelper::offsetFromLineCol($buffer, $line, $col),
        );
    }

    /**
     * @return list<array{0:string,1:int,2:int,3:int}>
     */
    public static function lineColCases(): array
    {
        return [
            ['', 0, 0, 0],
            ['ab', 0, 0, 0],
            ['ab', 2, 0, 2],
            ['ab', 99, 0, 2],
            ["a\nb", 0, 0, 0],
            ["a\nb", 2, 1, 0],
            ["a\nb", 3, 1, 1],
            ["a\nb", -3, 0, 0],
            ["я\nб", 2, 1, 0],
            ["\n", 1, 1, 0],
            ["a\n\nb", 2, 1, 0],
        ];
    }

    /**
     * Вертикальный шаг не выходит за первую и последнюю строки.
     */
    public function testMoveVerticallyHomeEnd(): void
    {
        $buffer = "ab\ncd";
        $this->assertSame(0, TuiInputBufferHelper::moveVertically($buffer, 0, -1));
        $this->assertSame(5, TuiInputBufferHelper::moveVertically($buffer, 5, 1));
        $this->assertSame(4, TuiInputBufferHelper::moveVertically($buffer, 1, 1));
        $this->assertSame(0, TuiInputBufferHelper::home($buffer, 2));
        $this->assertSame(3, TuiInputBufferHelper::home($buffer, 5));
        $this->assertSame(2, TuiInputBufferHelper::end($buffer, 0));
        $this->assertSame(5, TuiInputBufferHelper::end($buffer, 3));
        $this->assertSame(0, TuiInputBufferHelper::home('', -4));
        $this->assertSame(0, TuiInputBufferHelper::end('', 10));
    }

    /**
     * Viewport высотой 1 следует за курсором и не уходит выше последней строки.
     */
    public function testComputeViewport(): void
    {
        $buffer = "a\nb\nc";
        $this->assertSame([['c'], 2], TuiInputBufferHelper::computeViewport($buffer, 5, 0, 1));
        $this->assertSame([['a', 'b', 'c'], 0], TuiInputBufferHelper::computeViewport($buffer, 0, 99, 3));
        $this->assertSame([[''], 0], TuiInputBufferHelper::computeViewport('', 0, -5, 1));
        $this->assertSame([['a', 'b'], 0], TuiInputBufferHelper::computeViewport($buffer, 0, 0, 2));
    }
}
