<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\KeyEventDto;
use quanzo\tui\classes\tui\input\KeySequenceParser;
use quanzo\tui\classes\tui\input\Utf8CharReader;

/**
 * Тесты {@see KeySequenceParser} на потоке `php://memory`, без терминала.
 */
final class KeySequenceParserTest extends TestCase
{
    /**
     * Байтовая последовательность превращается в ожидаемый тип события.
     *
     * @param string $bytes
     * @param string|null $type null, если событие не распознано
     * @param string|null $text текст или payload paste, если есть
     */
    #[DataProvider('sequenceCases')]
    public function testReadEvent(string $bytes, ?string $type, ?string $text): void
    {
        $event = $this->read($bytes);
        if ($type === null) {
            $this->assertNull($event);
            return;
        }

        $this->assertInstanceOf(KeyEventDto::class, $event);
        $this->assertSame($type, $event->getType());
        if ($type === KeyEventDto::TYPE_TEXT) {
            $this->assertSame($text, $event->getText());
        }
        if ($type === KeyEventDto::TYPE_PASTE) {
            $this->assertSame($text, $event->getPasteText());
        }
        if ($type === KeyEventDto::TYPE_MOUSE) {
            $this->assertSame(0, $event->getMouseButton());
            $this->assertSame(1, $event->getMouseX());
            $this->assertSame(1, $event->getMouseY());
        }
    }

    /**
     * Пустой поток, обрывы, стрелки, Kitty, мышь и заведомо неизвестные ESC.
     *
     * @return list<array{0:string,1:?string,2:?string}>
     */
    public static function sequenceCases(): array
    {
        return [
            ['', null, null],
            ["\x03", KeyEventDto::TYPE_CTRL_C, null],
            ["\t", KeyEventDto::TYPE_TAB, null],
            ["\r", KeyEventDto::TYPE_ENTER, null],
            ["\n", KeyEventDto::TYPE_CTRL_ENTER, null],
            ["\x7f", KeyEventDto::TYPE_BACKSPACE, null],
            ["\033[A", KeyEventDto::TYPE_ARROW_UP, null],
            ["\033[B", KeyEventDto::TYPE_ARROW_DOWN, null],
            ["\033[C", KeyEventDto::TYPE_ARROW_RIGHT, null],
            ["\033[D", KeyEventDto::TYPE_ARROW_LEFT, null],
            ["\033OA", KeyEventDto::TYPE_ARROW_UP, null],
            ["\033[5~", KeyEventDto::TYPE_PAGE_UP, null],
            ["\033[6~", KeyEventDto::TYPE_PAGE_DOWN, null],
            ["\033[1~", KeyEventDto::TYPE_HOME, null],
            ["\033[4~", KeyEventDto::TYPE_END, null],
            ["\033[3~", KeyEventDto::TYPE_DELETE, null],
            ["\033[H", KeyEventDto::TYPE_HOME, null],
            ["\033[F", KeyEventDto::TYPE_END, null],
            ["\033OH", KeyEventDto::TYPE_HOME, null],
            ["\033OQ", KeyEventDto::TYPE_TOGGLE_MOUSE_MODE, null],
            ["\033[12~", KeyEventDto::TYPE_TOGGLE_MOUSE_MODE, null],
            ["\033[13;5u", KeyEventDto::TYPE_CTRL_ENTER, null],
            ["\033[13u", KeyEventDto::TYPE_ENTER, null],
            ["\033", null, null],
            ["\033[", null, null],
            ["\033[Z", null, null],
            ["\033OZ", null, null],
            ["\033X", null, null],
            ['я', KeyEventDto::TYPE_TEXT, 'я'],
            ["\033[200~a\r\nb\033[201~", KeyEventDto::TYPE_PASTE, "a\nb"],
            ["\033[M !!", KeyEventDto::TYPE_MOUSE, null],
            ["\033[M", null, null],
            ["\033[M\x00", null, null],
        ];
    }

    /**
     * Bracketed paste без маркера конца не должен зависнуть дольше deadline парсера.
     */
    public function testUnclosedBracketedPasteReturnsNull(): void
    {
        $started = microtime(true);
        $event = $this->read("\033[200~hello");
        $elapsed = microtime(true) - $started;

        $this->assertNull($event);
        $this->assertLessThan(4.0, $elapsed);
    }

    /**
     * @param string $bytes
     * @return KeyEventDto|null
     */
    private function read(string $bytes): ?KeyEventDto
    {
        $stdin = fopen('php://memory', 'r+');
        $this->assertIsResource($stdin);
        fwrite($stdin, $bytes);
        rewind($stdin);

        try {
            return (new KeySequenceParser(new Utf8CharReader()))->readEvent($stdin);
        } finally {
            fclose($stdin);
        }
    }
}
