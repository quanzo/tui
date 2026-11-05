<?php

declare(strict_types=1);

namespace Tests\Tui;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\helpers\TuiTimeHelper;

/**
 * Тесты {@see TuiTimeHelper}: границы эпохи, отрицательные метки и крайние int.
 */
final class TuiTimeHelperTest extends TestCase
{
    /**
     * Миллисекунды переводятся в локальное `Y-m-d H:i:s` с округлением секунды вниз.
     *
     * Ожидаемые строки посчитаны в UTC. Слишком большая и слишком маленькая метка тоже остаются датой,
     * пока `DateTimeImmutable` её принимает.
     *
     * @param int $epochMs
     * @param string $expected
     */
    #[DataProvider('epochCases')]
    public function testFormatEpochMs(int $epochMs, string $expected): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            $this->assertSame($expected, TuiTimeHelper::formatEpochMs($epochMs));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    /**
     * @return list<array{0:int,1:string}>
     */
    public static function epochCases(): array
    {
        return [
            [0, '1970-01-01 00:00:00'],
            [999, '1970-01-01 00:00:00'],
            [1000, '1970-01-01 00:00:01'],
            [1500, '1970-01-01 00:00:01'],
            [-1, '1969-12-31 23:59:59'],
            [-1000, '1969-12-31 23:59:59'],
            [-1001, '1969-12-31 23:59:58'],
            [86_399_999, '1970-01-01 23:59:59'],
            [86_400_000, '1970-01-02 00:00:00'],
            [1_700_000_000_000, '2023-11-14 22:13:20'],
            [PHP_INT_MAX, '292278994-08-17 07:12:55'],
            [PHP_INT_MIN, '-292275055-05-16 16:47:04'],
        ];
    }
}
