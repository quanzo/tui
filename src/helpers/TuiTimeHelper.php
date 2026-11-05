<?php

declare(strict_types=1);

namespace quanzo\tui\helpers;

/**
 * Хелпер форматирования времени, не привязанный к handler'ам и менеджеру задач.
 *
 * Пример использования:
 *
 * ```php
 * $text = TuiTimeHelper::formatEpochMs(1_700_000_000_000);
 * ```
 */
final class TuiTimeHelper
{
    /**
     * Форматирует epoch в миллисекундах как локальное время `Y-m-d H:i:s`.
     *
     * Секунда считается через floor деления на 1000. Часовой пояс — `date_default_timezone_get()`.
     * Если `DateTimeImmutable` не принимает метку, возвращается исходное число, чтобы ячейка таблицы не пустела.
     *
     * @param int $epochMs Метка времени в миллисекундах с эпохи Unix. Может быть отрицательной.
     * @return string
     */
    public static function formatEpochMs(int $epochMs): string
    {
        $seconds = self::floorDiv($epochMs, 1000);

        try {
            $utc = new \DateTimeImmutable('@' . $seconds);
            $local = $utc->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            return $local->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return (string) $epochMs;
        }
    }

    /**
     * Целочисленное деление с округлением к минус бесконечности.
     *
     * `intdiv()` обрезает к нулю, поэтому для отрицательного остатка частное уменьшается на 1.
     *
     * @param int $value Делимое
     * @param int $divisor Делитель, не ноль
     * @return int
     */
    private static function floorDiv(int $value, int $divisor): int
    {
        $quotient = intdiv($value, $divisor);
        $remainder = $value % $divisor;
        if ($remainder !== 0 && $value < 0) {
            return $quotient - 1;
        }

        return $quotient;
    }
}
