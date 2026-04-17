<?php

namespace quanzo\tui\classes\status;

use quanzo\tui\interfaces\StatusInterface;

/**
 * Пустой статус – ничего не выводит.
 * Используется как заглушка или для отключения вывода.
 */
class EmptyStatus implements StatusInterface
{
    /**
     * @inheritDoc
     */
    public function getText(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getColorCode(): string
    {
        return "\033[0m"; // сброс цвета
    }
}
