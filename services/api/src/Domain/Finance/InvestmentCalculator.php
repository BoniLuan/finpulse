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

    /** Future value of a deposit compounding at an annual rate (e.g. Tesouro Selic ≈ Selic). */
    public function treasurySelicReturn(float $principal, float $annualSelicPct, int $months): float
    {
        return $this->futureValue($principal, $this->annualToMonthlyPct($annualSelicPct), $months);
    }

    /**
     * Future value of a CDB yielding a percentage of the CDI rate.
     *
     * @param float $percentOfCdi e.g. 110 for "110% of CDI"
     */
    public function cdbReturn(float $principal, float $annualCdiPct, float $percentOfCdi, int $months): float
    {
        $effectiveAnnual = $annualCdiPct * $percentOfCdi / 100.0;

        return $this->futureValue($principal, $this->annualToMonthlyPct($effectiveAnnual), $months);
    }
}
