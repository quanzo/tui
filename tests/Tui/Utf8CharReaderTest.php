<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\tui\input\Utf8CharReader;

/**
 * Тесты {@see Utf8CharReader}: ASCII, многобайтовые символы и обрыв последовательности.
 */
final class Utf8CharReaderTest extends TestCase
{
    /**
     * Один символ или null, если байтов не хватает на символ.
     *
     * @param string $bytes
     * @param string|null $expected
     */
    #[DataProvider('readCases')]
    public function testReadChar(string $bytes, ?string $expected): void
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $bytes);
        rewind($stream);

        try {
            $this->assertSame($expected, (new Utf8CharReader())->readChar($stream));
        } finally {
            fclose($stream);
        }
    }

    /**
     * Пустой поток, валидный UTF-8, обрыв и байт продолжения, который читатель не проверяет.
     *
     * @return list<array{0:string,1:?string}>
     */
    public static function readCases(): array
    {
        return [
            ['', null],
            ['a', 'a'],
            ["\x7f", "\x7f"],
            ['я', 'я'],
            ['€', '€'],
            ['😀', '😀'],
            ["\xC3", null],
            ["\xE2\x82", null],
            ["\xF0\x9F\x98", null],
            ["\xFF", null],
            ["\xC3 ", "\xC3 "],
            ["\x80", null],
        ];
    }
}
