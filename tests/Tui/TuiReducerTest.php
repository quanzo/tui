<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\KeyEventDto;
use quanzo\tui\classes\dto\tui\LayoutDto;
use quanzo\tui\classes\dto\tui\TuiStateDto;
use quanzo\tui\classes\tui\state\TuiReducer;

/**
 * Тесты {@see TuiReducer}: фокус, submit, границы курсора, прокрутка и мышь.
 */
final class TuiReducerTest extends TestCase
{
    /**
     * В фокусе просмотра правки буфера игнорируются, включая пустой текст.
     *
     * @param KeyEventDto $event
     */
    #[DataProvider('ignoredInOutputCases')]
    public function testEditingIgnoredWhenFocusIsOutput(KeyEventDto $event): void
    {
        $state = (new TuiStateDto())
            ->setFocus(TuiStateDto::FOCUS_OUTPUT)
            ->setInputBuffer('keep')
            ->setCursorOffset(2)
            ->setOutputScroll(3);

        $result = (new TuiReducer())->applyKeyEventWithResult($state, $event, $this->layout());

        $this->assertNull($result->getSubmittedInput());
        $this->assertSame('keep', $result->getState()->getInputBuffer());
        $this->assertSame(2, $result->getState()->getCursorOffset());
        $this->assertSame(3, $result->getState()->getOutputScroll());
    }

    /**
     * @return list<array{0:KeyEventDto}>
     */
    public static function ignoredInOutputCases(): array
    {
        return [
            [KeyEventDto::text('я')],
            [KeyEventDto::text('')],
            [KeyEventDto::paste("a\nb")],
            [KeyEventDto::paste('')],
            [KeyEventDto::backspace()],
            [KeyEventDto::delete()],
            [KeyEventDto::home()],
            [KeyEventDto::end()],
            [KeyEventDto::ctrlEnter()],
            [KeyEventDto::arrowLeft()],
            [KeyEventDto::arrowRight()],
            [KeyEventDto::enter()],
        ];
    }

    /**
     * Tab переключает фокус в обе стороны и требует полную перерисовку.
     */
    public function testTabTogglesFocus(): void
    {
        $reducer = new TuiReducer();
        $state = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_INPUT)->setFullRedraw(false);

        $state = $reducer->applyKeyEvent($state, KeyEventDto::tab(), $this->layout());
        $this->assertSame(TuiStateDto::FOCUS_OUTPUT, $state->getFocus());
        $this->assertTrue($state->isFullRedraw());

        $state = $reducer->applyKeyEvent($state, KeyEventDto::tab(), $this->layout());
        $this->assertSame(TuiStateDto::FOCUS_INPUT, $state->getFocus());
    }

    /**
     * Enter отправляет буфер, пустой и повторный submit не раздувают историю команд.
     */
    public function testEnterSubmitsAndSkipsEmptyOrDuplicateHistory(): void
    {
        $reducer = new TuiReducer();
        $layout = $this->layout();
        $state = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_INPUT)->setInputBuffer('hi')->setCursorOffset(2);

        $first = $reducer->applyKeyEventWithResult($state, KeyEventDto::enter(), $layout);
        $this->assertSame('hi', $first->getSubmittedInput());
        $this->assertSame('', $first->getState()->getInputBuffer());
        $this->assertSame(['hi'], $first->getState()->getInputHistory());

        $first->getState()->setInputBuffer('hi')->setCursorOffset(2);
        $dup = $reducer->applyKeyEventWithResult($first->getState(), KeyEventDto::enter(), $layout);
        $this->assertSame(['hi'], $dup->getState()->getInputHistory());

        $dup->getState()->setInputBuffer('   ')->setCursorOffset(3);
        $blank = $reducer->applyKeyEventWithResult($dup->getState(), KeyEventDto::enter(), $layout);
        $this->assertSame('   ', $blank->getSubmittedInput());
        $this->assertSame(['hi'], $blank->getState()->getInputHistory());

        $blank->getState()->setInputBuffer('')->setCursorOffset(0);
        $empty = $reducer->applyKeyEventWithResult($blank->getState(), KeyEventDto::enter(), $layout);
        $this->assertSame('', $empty->getSubmittedInput());
        $this->assertSame(['hi'], $empty->getState()->getInputHistory());
    }

    /**
     * Backspace в нуле и Delete в конце не портят буфер. Home/End держатся строки.
     */
    public function testBackspaceDeleteHomeEndAtEdges(): void
    {
        $reducer = new TuiReducer();
        $layout = $this->layout();
        $state = (new TuiStateDto())
            ->setFocus(TuiStateDto::FOCUS_INPUT)
            ->setInputBuffer("ab\ncd")
            ->setCursorOffset(0);

        $state = $reducer->applyKeyEvent($state, KeyEventDto::backspace(), $layout);
        $this->assertSame("ab\ncd", $state->getInputBuffer());
        $this->assertSame(0, $state->getCursorOffset());

        $state->setCursorOffset(5);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::delete(), $layout);
        $this->assertSame("ab\ncd", $state->getInputBuffer());

        $state->setCursorOffset(5);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::home(), $layout);
        $this->assertSame(3, $state->getCursorOffset());

        $state = $reducer->applyKeyEvent($state, KeyEventDto::end(), $layout);
        $this->assertSame(5, $state->getCursorOffset());
    }

    /**
     * Ctrl+C останавливает цикл даже в режиме просмотра.
     */
    public function testCtrlCStopsFromAnyFocus(): void
    {
        $state = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_OUTPUT)->setRunning(true);
        $state = (new TuiReducer())->applyKeyEvent($state, KeyEventDto::ctrlC(), $this->layout());
        $this->assertFalse($state->isRunning());
    }

    /**
     * Стрелки ввода не уходят левее 0 и правее конца строки.
     */
    public function testArrowLeftAndRightClamp(): void
    {
        $reducer = new TuiReducer();
        $layout = $this->layout();
        $state = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_INPUT)->setInputBuffer('ab')->setCursorOffset(0);

        $state = $reducer->applyKeyEvent($state, KeyEventDto::arrowLeft(), $layout);
        $this->assertSame(0, $state->getCursorOffset());

        $state->setCursorOffset(2);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::arrowRight(), $layout);
        $this->assertSame(2, $state->getCursorOffset());

        $state = $reducer->applyKeyEvent($state, KeyEventDto::arrowLeft(), $layout);
        $this->assertSame(1, $state->getCursorOffset());
    }

    /**
     * PageUp/PageDown работают только в просмотре. PageUp не уходит ниже нуля.
     */
    public function testPageScrollOnlyInOutput(): void
    {
        $reducer = new TuiReducer();
        $layout = $this->layout();
        $input = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_INPUT)->setOutputScroll(4);
        $input = $reducer->applyKeyEvent($input, KeyEventDto::pageUp(), $layout);
        $input = $reducer->applyKeyEvent($input, KeyEventDto::pageDown(), $layout);
        $this->assertSame(4, $input->getOutputScroll());

        $output = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_OUTPUT)->setOutputScroll(3);
        $output = $reducer->applyKeyEvent($output, KeyEventDto::pageUp(), $layout);
        $this->assertSame(0, $output->getOutputScroll());

        $output = $reducer->applyKeyEvent($output, KeyEventDto::pageDown(), $layout);
        $this->assertSame(8, $output->getOutputScroll());
    }

    /**
     * Мышь без режима и с неположительными координатами не меняет фокус.
     */
    public function testMouseIgnoredWhenDisabledOrInvalid(): void
    {
        $reducer = new TuiReducer();
        $layout = $this->layout();
        $state = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_INPUT)->setMouseModeEnabled(false);

        $state = $reducer->applyKeyEvent($state, KeyEventDto::mouse(0, 2, 2), $layout);
        $this->assertSame(TuiStateDto::FOCUS_INPUT, $state->getFocus());

        $state->setMouseModeEnabled(true);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::mouse(0, 0, 2), $layout);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::mouse(0, 2, 0), $layout);
        $state = $reducer->applyKeyEvent($state, KeyEventDto::mouse(0, -1, -1), $layout);
        $this->assertSame(TuiStateDto::FOCUS_INPUT, $state->getFocus());
    }

    /**
     * Клик по выводу переносит фокус, клик по вводу ставит курсор.
     */
    public function testMouseClickMovesFocusAndCursor(): void
    {
        $reducer = new TuiReducer();
        $layout = $this->layout();
        $state = (new TuiStateDto())
            ->setFocus(TuiStateDto::FOCUS_INPUT)
            ->setMouseModeEnabled(true)
            ->setInputBuffer('abcd')
            ->setCursorOffset(0);

        $state = $reducer->applyKeyEvent($state, KeyEventDto::mouse(0, 2, 2), $layout);
        $this->assertSame(TuiStateDto::FOCUS_OUTPUT, $state->getFocus());

        $state = $reducer->applyKeyEvent($state, KeyEventDto::mouse(0, 4, 12), $layout);
        $this->assertSame(TuiStateDto::FOCUS_INPUT, $state->getFocus());
        $this->assertSame(2, $state->getCursorOffset());
    }

    /**
     * ArrowUp при пустой истории команд не подменяет черновик.
     */
    public function testArrowUpOnEmptyInputHistoryKeepsDraft(): void
    {
        $state = (new TuiStateDto())
            ->setFocus(TuiStateDto::FOCUS_INPUT)
            ->setInputBuffer('draft')
            ->setCursorOffset(5);

        $state = (new TuiReducer())->applyKeyEvent($state, KeyEventDto::arrowUp(), $this->layout());
        $this->assertSame('draft', $state->getInputBuffer());
        $this->assertNull($state->getInputHistoryIndex());
    }

    /**
     * Прокрутка вывода стрелкой вверх не уходит в минус.
     */
    public function testArrowUpInOutputClampsScroll(): void
    {
        $state = (new TuiStateDto())->setFocus(TuiStateDto::FOCUS_OUTPUT)->setOutputScroll(0);
        $state = (new TuiReducer())->applyKeyEvent($state, KeyEventDto::arrowUp(), $this->layout());
        $this->assertSame(0, $state->getOutputScroll());
    }

    /**
     * Минимальная геометрия: 9 видимых строк вывода, страница прокрутки = 8.
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
