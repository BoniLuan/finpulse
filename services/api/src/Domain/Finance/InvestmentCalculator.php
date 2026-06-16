<?php

declare(strict_types=1);

namespace FinPulse\Domain\Finance;

/**
 * Compound-interest math. Pure and fully unit-testable — no IO, no framework.
 */
final class InvestmentCalculator
{
    /**
     * Future value of a single deposit under monthly compounding.
     *
     * @param float $principal      initial amount
     * @param float $monthlyRatePct monthly rate in percent (e.g. 0.5 for 0.5%/mo)
     * @param int   $months         number of months
     */
    public function futureValue(float $principal, float $monthlyRatePct, int $months): float
    {
        if ($principal < 0) {
            throw new \InvalidArgumentException('principal must be >= 0');
        }
        if ($months < 0) {
            throw new \InvalidArgumentException('months must be >= 0');
        }

        $rate = $monthlyRatePct / 100.0;

        return round($principal * (1 + $rate) ** $months, 2);
    }

    /** Convert an annual percentage rate to its equivalent monthly percentage. */
    public function annualToMonthlyPct(float $annualRatePct): float
    {
        $annual = $annualRatePct / 100.0;
        $monthly = (1 + $annual) ** (1 / 12) - 1;

        return $monthly * 100.0;
    }
}
