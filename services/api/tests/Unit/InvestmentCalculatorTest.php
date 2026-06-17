<?php

declare(strict_types=1);

namespace FinPulse\Tests\Unit;

use FinPulse\Domain\Finance\InvestmentCalculator;
use PHPUnit\Framework\TestCase;

final class InvestmentCalculatorTest extends TestCase
{
    private InvestmentCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new InvestmentCalculator();
    }

    public function testFutureValueCompoundsMonthly(): void
    {
        // 1000 at 1%/month for 12 months → 1000 * 1.01^12 ≈ 1126.83
        self::assertSame(1126.83, $this->calc->futureValue(1000, 1.0, 12));
    }

    public function testZeroMonthsReturnsPrincipal(): void
    {
        self::assertSame(500.0, $this->calc->futureValue(500, 1.0, 0));
    }

    public function testAnnualToMonthlyConversion(): void
    {
        // 12.6825% per year ≈ 1%/month
        self::assertEqualsWithDelta(1.0, $this->calc->annualToMonthlyPct(12.6825), 0.001);
    }

    public function testNegativePrincipalRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->calc->futureValue(-1, 1.0, 12);
    }

    public function testTreasurySelicReturn(): void
    {
        // 12.6825% a year for 12 months → 1000 * 1.126825 ≈ 1126.83
        self::assertEqualsWithDelta(1126.83, $this->calc->treasurySelicReturn(1000, 12.6825, 12), 0.05);
    }

    public function testCdbReturnAt100PercentMatchesPlainRate(): void
    {
        self::assertEqualsWithDelta(1126.83, $this->calc->cdbReturn(1000, 12.6825, 100, 12), 0.05);
    }

    public function testCdbReturnAt110PercentYieldsMore(): void
    {
        // effective annual = 12.6825 * 1.10 = 13.95075% → 1000 * 1.1395075 ≈ 1139.51
        self::assertEqualsWithDelta(1139.51, $this->calc->cdbReturn(1000, 12.6825, 110, 12), 0.05);
    }
}
