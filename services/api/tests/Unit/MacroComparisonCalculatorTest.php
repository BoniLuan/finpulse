<?php

declare(strict_types=1);

namespace FinPulse\Tests\Unit;

use FinPulse\Domain\Finance\MacroComparisonCalculator;
use PHPUnit\Framework\TestCase;

final class MacroComparisonCalculatorTest extends TestCase
{
    public function testCalculatesMacroSummaryFromMonthlySeries(): void
    {
        $summary = (new MacroComparisonCalculator())->compare(
            [
                ['date' => '2026-01-02', 'value' => 10.0],
                ['date' => '2026-01-20', 'value' => 10.0],
                ['date' => '2026-02-01', 'value' => 12.0],
                ['date' => '2026-03-01', 'value' => 14.0],
            ],
            [
                ['date' => '2026-01-01', 'value' => 1.0],
                ['date' => '2026-02-01', 'value' => 2.0],
                ['date' => '2026-03-01', 'value' => 3.0],
            ],
        );

        self::assertSame(10.0, $summary['selic_start_pct']);
        self::assertSame(14.0, $summary['selic_latest_pct']);
        self::assertSame(4.0, $summary['selic_change_pp']);
        self::assertEqualsWithDelta(6.1106, $summary['ipca_accumulated_pct'], 0.0001);
        self::assertEqualsWithDelta(7.4349, $summary['real_rate_latest_pct'], 0.001);
        self::assertSame(1.0, $summary['monthly_correlation']);
    }

    public function testReturnsNullMetricsForEmptySeries(): void
    {
        $summary = (new MacroComparisonCalculator())->compare([], []);

        self::assertNull($summary['selic_latest_pct']);
        self::assertNull($summary['ipca_accumulated_pct']);
        self::assertNull($summary['real_rate_latest_pct']);
        self::assertNull($summary['monthly_correlation']);
    }
}
