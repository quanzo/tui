<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\CodeBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\DividerBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\HeadingBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\KeyHintsBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\ListBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\PanelBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TableBlockDto;
use quanzo\tui\classes\dto\tui\view\blocks\TextBlockDto;
use quanzo\tui\classes\dto\tui\view\TuiBlockOptionsDto;
use quanzo\tui\classes\dto\tui\view\TuiThemeDto;
use quanzo\tui\classes\tui\render\TuiHistoryFormatter;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\helpers\TuiTextHelper;
use quanzo\tui\interfaces\tui\view\TuiBlockInterface;

/**
 * Тесты {@see TuiHistoryFormatter}: пустая история, перенос, таблица и повторный рендер панели.
 */
final class TuiHistoryFormatterTest extends TestCase
{
    /**
     * Пустая история и запись только с plain text.
     */
    public function testEmptyHistoryAndPlainTextFallback(): void
    {
        $formatter = new TuiHistoryFormatter();
        $theme = new TuiThemeDto();

        $this->assertSame([], $formatter->toDisplayLines(new TuiHistoryDto(), 20, $theme));

        $history = (new TuiHistoryDto())->append(TuiHistoryEntryDto::output('plain'));
        $this->assertSame(['plain'], $this->plain($formatter->toDisplayLines($history, 20, $theme)));

        $blank = (new TuiHistoryDto())->append(TuiHistoryEntryDto::output('')->setPlainText(''));
        $this->assertSame([], $formatter->toDisplayLines($blank, 20, $theme));
    }

    /**
     * Две записи разделяются пустой строкой, хвостовой разделитель не остаётся.
     */
    public function testEntriesAreSeparatedWithoutTrailingBlank(): void
    {
        $history = (new TuiHistoryDto())
            ->append(TuiHistoryEntryDto::output('a'))
            ->append(TuiHistoryEntryDto::output('b'));

        $lines = (new TuiHistoryFormatter())->toDisplayLines($history, 10, new TuiThemeDto());
        $this->assertSame(['a', '', 'b'], $lines);
    }

    /**
     * Текст с wrap режется, без wrap обрезается многоточием. Ширина 0 не падает.
     */
    public function testTextWrapTrimAndZeroWidth(): void
    {
        $formatter = new TuiHistoryFormatter();
        $theme = new TuiThemeDto();

        $wrapped = (new TextBlockDto('hello'))->setOptions((new TuiBlockOptionsDto())->setWrap(true));
        $history = (new TuiHistoryDto())->append(TuiHistoryEntryDto::output('t')->setBlocks([$wrapped]));
        $this->assertSame(['he', 'll', 'o'], $formatter->toDisplayLines($history, 2, $theme));

        $trimmed = (new TextBlockDto('hello'))->setOptions((new TuiBlockOptionsDto())->setWrap(false));
        $history = (new TuiHistoryDto())->append(TuiHistoryEntryDto::output('t')->setBlocks([$trimmed]));
        $this->assertSame(['hel…'], $this->plain($formatter->toDisplayLines($history, 4, $theme)));

        $wide = (new TextBlockDto('hello'))->setOptions((new TuiBlockOptionsDto())->setWrap(true));
        $history = (new TuiHistoryDto())->append(TuiHistoryEntryDto::output('t')->setBlocks([$wide]));
        $this->assertSame(['hello'], $formatter->toDisplayLines($history, 0, $theme));
    }

    /**
     * Метки notice для четырёх видов и пустого текста.
     *
     * @param TuiNoticeKindEnum $kind
     * @param string $text
     * @param string $expected
     */
    #[DataProvider('noticeCases')]
    public function testNoticeLabels(TuiNoticeKindEnum $kind, string $text, string $expected): void
    {
        $history = (new TuiHistoryDto())->append(
            TuiHistoryEntryDto::output('n')->setBlocks([new NoticeBlockDto($kind, $text)]),
        );
        $lines = $this->plain((new TuiHistoryFormatter())->toDisplayLines($history, 40, new TuiThemeDto()));
        $this->assertSame([$expected], $lines);
    }

    /**
     * @return list<array{0:TuiNoticeKindEnum,1:string,2:string}>
     */
    public static function noticeCases(): array
    {
        return [
            [TuiNoticeKindEnum::Info, 'ok', '[INFO] ok'],
            [TuiNoticeKindEnum::Success, 'ok', '[OK] ok'],
            [TuiNoticeKindEnum::Warning, 'ok', '[WARN] ok'],
            [TuiNoticeKindEnum::Error, 'ok', '[ERR] ok'],
            [TuiNoticeKindEnum::Info, '', '[INFO] '],
            [TuiNoticeKindEnum::Success, '', '[OK] '],
            [TuiNoticeKindEnum::Warning, '', '[WARN] '],
            [TuiNoticeKindEnum::Error, '', '[ERR] '],
            [TuiNoticeKindEnum::Info, '0', '[INFO] 0'],
            [TuiNoticeKindEnum::Error, 'я', '[ERR] я'],
        ];
    }

    /**
     * Заголовок, разделитель, список, подсказки и неизвестный тип блока.
     */
    public function testHeadingDividerListHintsAndUnknownBlock(): void
    {
        $formatter = new TuiHistoryFormatter();
        $theme = new TuiThemeDto();

        $heading = (new HeadingBlockDto('Ab'))->setUnderline(true);
        $lines = $this->plain($formatter->toDisplayLines($this->historyOf($heading), 10, $theme));
        $this->assertSame(['Ab', '──'], $lines);

        $divider = (new DividerBlockDto())->setChar('-');
        $lines = $this->plain($formatter->toDisplayLines($this->historyOf($divider), 4, $theme));
        $this->assertSame(['----'], $lines);

        $list = (new ListBlockDto(['abcd']))->setBullet('*');
        $lines = $formatter->toDisplayLines($this->historyOf($list), 4, $theme);
        $this->assertSame(['* ab', '  cd'], $lines);

        $hints = new KeyHintsBlockDto(['a', 'b']);
        $lines = $this->plain($formatter->toDisplayLines($this->historyOf($hints), 20, $theme));
        $this->assertSame(['a | b'], $lines);

        $unknown = new class implements TuiBlockInterface {
            public function getType(): string
            {
                return 'nope';
            }
        };
        $lines = $formatter->toDisplayLines($this->historyOf($unknown), 40, $theme);
        $this->assertSame(['[unsupported block: nope]'], $lines);
    }

    /**
     * Короткая строка таблицы дополняется, узкая таблица режет последнюю колонку многоточием.
     */
    public function testTableShortRowAndNarrowLastColumn(): void
    {
        $formatter = new TuiHistoryFormatter();
        $theme = new TuiThemeDto();

        $short = new TableBlockDto(['A', 'B'], [['only']]);
        $lines = $this->plain($formatter->toDisplayLines($this->historyOf($short), 40, $theme));
        $joined = implode("\n", $lines);
        $this->assertStringContainsString('only', $joined);
        $this->assertStringContainsString('|', $joined);

        $narrow = new TableBlockDto(['AAAA', 'BBBBBBBB'], [['1111', '22222222']]);
        $lines = $this->plain($formatter->toDisplayLines($this->historyOf($narrow), 10, $theme));
        $data = $lines[count($lines) - 1];
        $this->assertStringContainsString('…', $data);
        $this->assertGreaterThan(10, TuiTextHelper::visibleWidth($data));
    }

    /**
     * Блок кода показывает язык и номера строк.
     */
    public function testCodeBlockLanguageAndLineNumbers(): void
    {
        $code = (new CodeBlockDto("a\r\nb"))->setLanguage('php')->setLineNumbers(true);
        $lines = $this->plain((new TuiHistoryFormatter())->toDisplayLines($this->historyOf($code), 20, new TuiThemeDto()));
        $this->assertSame(['[php]', '1 a', '2 b'], $lines);
    }

    /**
     * Повторный рендер панели не накапливает отступ у вложенного блока.
     */
    public function testPanelDoesNotAccumulateIndent(): void
    {
        $child = new TextBlockDto('x');
        $panel = new PanelBlockDto('Title', [$child]);
        $formatter = new TuiHistoryFormatter();
        $theme = new TuiThemeDto();

        $formatter->toDisplayLines($this->historyOf($panel), 20, $theme);
        $formatter->toDisplayLines($this->historyOf($panel), 20, $theme);

        $this->assertSame(0, $child->getOptions()->getIndent());
        $lines = $this->plain($formatter->toDisplayLines($this->historyOf($panel), 20, $theme));
        $this->assertSame('  x', $lines[count($lines) - 1]);
    }

    /**
     * @param TuiBlockInterface $block
     */
    private function historyOf(TuiBlockInterface $block): TuiHistoryDto
    {
        return (new TuiHistoryDto())->append(TuiHistoryEntryDto::output('b')->setBlocks([$block]));
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function plain(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $out[] = TuiTextHelper::stripAnsi($line);
        }
        return $out;
    }
}
