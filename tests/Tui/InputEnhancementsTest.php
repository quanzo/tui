<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\KeyEventDto;
use quanzo\tui\classes\dto\tui\LayoutDto;
use quanzo\tui\classes\dto\tui\TuiStateDto;
use quanzo\tui\classes\tui\input\KeySequenceParser;
use quanzo\tui\classes\tui\input\Utf8CharReader;
use quanzo\tui\classes\tui\state\TuiReducer;

/**
 * Тесты UX-улучшений ввода: Ctrl+J как «Ctrl+Enter» и history по ArrowUp/ArrowDown.
 */
final class InputEnhancementsTest extends TestCase
{
    /**
     * Ctrl+J должен распознаваться как отдельное событие (ctrl_enter), а не как Enter submit.
     */
    public function testCtrlJParsedAsCtrlEnter(): void
    {
        $parser = new KeySequenceParser(new Utf8CharReader());
        $stdin = fopen('php://memory', 'r+');
        fwrite($stdin, "\n");
        rewind($stdin);

        $evt = $parser->readEvent($stdin);
        $this->assertInstanceOf(KeyEventDto::class, $evt);
        $this->assertSame(KeyEventDto::TYPE_CTRL_ENTER, $evt->getType());
    }

    /**
     * Ctrl+J вставляет новую строку в буфер и не приводит к submit.
     */
    public function testCtrlEnterInsertsNewlineWithoutSubmit(): void
    {
        $state = (new TuiStateDto())
            ->setFocus(TuiStateDto::FOCUS_INPUT)
            ->setInputBuffer('a')
            ->setCursorOffset(1);

        $layout = $this->layout();
        $reducer = new TuiReducer();
        $res = $reducer->applyKeyEventWithResult($state, KeyEventDto::ctrlEnter(), $layout);

        $this->assertSame("a\n", $res->getState()->getInputBuffer());
        $this->assertNull($res->getSubmittedInput());
    }

    /**
     * ArrowUp/ArrowDown в первой строке листает историю и восстанавливает draft на выходе.
     */
    public function testInputHistoryUpDownRestoresDraft(): void
    {
        $layout = $this->layout();
        $reducer = new TuiReducer();

        $state = (new TuiStateDto())
            ->setFocus(TuiStateDto::FOCUS_INPUT)
            ->setInputBuffer('first')
            ->setCursorOffset(5);

        // Submit first command -> history: ["first"], input cleared.
        $res1 = $reducer->applyKeyEventWithResult($state, KeyEventDto::enter(), $layout);
        $this->assertSame('first', $res1->getSubmittedInput());
        $state = $res1->getState();
        $this->assertSame('', $state->getInputBuffer());

        // Draft something and go into history.
        $state->setInputBuffer('dr')->setCursorOffset(2)->setInputViewportTopLine(0);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::arrowUp(), $layout);
        $this->assertSame('first', $state->getInputBuffer());
        $this->assertSame(5, $state->getCursorOffset());
        $this->assertSame(0, $state->getInputHistoryIndex());

        // ArrowDown should restore draft.
        $state = $reducer->applyKeyEvent($state, KeyEventDto::arrowDown(), $layout);
        $this->assertSame('dr', $state->getInputBuffer());
        $this->assertSame(2, $state->getCursorOffset());
        $this->assertNull($state->getInputHistoryIndex());
    }

    /**
     * Минимальный layout для reducer-тестов (видимые линии output не критичны для input UX).
     */
    private function layout(): LayoutDto
    {
        return new LayoutDto(
            outputContentStart: 2,
            outputContentEnd: 10,
            inputContentStart: 12,
            inputContentEnd: 14,
            statusLine: 15,
        );
    }
}
