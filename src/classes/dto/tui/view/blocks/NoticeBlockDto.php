<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\view\blocks;

use quanzo\tui\classes\dto\tui\view\TuiBlockOptionsDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\view\TuiBlockInterface;

/**
 * Блок уведомления (info/success/warning/error).
 *
 * Пример использования:
 *
 * ```php
 * $n = new NoticeBlockDto(TuiNoticeKindEnum::Error, 'Ошибка');
 * ```
 */
final class NoticeBlockDto implements TuiBlockInterface
{
    public const TYPE = 'notice';

    private TuiBlockOptionsDto $options;

    public function __construct(
        private readonly TuiNoticeKindEnum $kind,
        private readonly string $text,
    ) {
        $this->options = new TuiBlockOptionsDto();
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getKind(): TuiNoticeKindEnum
    {
        return $this->kind;
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
