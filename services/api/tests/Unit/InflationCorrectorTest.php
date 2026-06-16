<?php

declare(strict_types=1);

namespace FinPulse\Tests\Unit;

use FinPulse\Domain\Finance\InflationCorrector;
use PHPUnit\Framework\TestCase;

final class InflationCorrectorTest extends TestCase
{
    public function testCorrectsByAccumulatedInflation(): void
    {
        $result = (new InflationCorrector())->correct(1000, [1.0, 1.0]);

        // 1000 * 1.01 * 1.01 = 1020.10
        self::assertSame(1020.10, $result['corrected']);
        self::assertEqualsWithDelta(2.01, $result['accumulated_pct'], 0.001);
    }

    public function testEmptySeriesLeavesAmountUnchanged(): void
    {
        $result = (new InflationCorrector())->correct(750, []);

        self::assertSame(750.0, $result['corrected']);
        self::assertSame(1.0, $result['factor']);
    }
}
