<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\view\blocks;

use quanzo\tui\classes\dto\tui\view\TuiBlockOptionsDto;
use quanzo\tui\interfaces\tui\view\TuiBlockInterface;

/**
 * Блок обычного текста.
 *
 * Пример использования:
 *
 * ```php
 * $block = (new TextBlockDto('Hello'))->setOptions((new TuiBlockOptionsDto())->setIndent(2));
 * ```
 */
final class TextBlockDto implements TuiBlockInterface
{
    public const TYPE = 'text';

    private TuiBlockOptionsDto $options;

    public function __construct(
        private readonly string $text,
    ) {
        $this->options = new TuiBlockOptionsDto();
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getOptions(): TuiBlockOptionsDto
    {
        return $this->options;
    }

    public function setOptions(TuiBlockOptionsDto $options): self
    {
        $this->options = $options;
        return $this;
    }
}
