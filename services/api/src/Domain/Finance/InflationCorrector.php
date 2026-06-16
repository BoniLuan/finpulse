<?php

declare(strict_types=1);

namespace FinPulse\Domain\Finance;

/**
 * Corrects nominal values by accumulated inflation. Pure — no IO.
 */
final class InflationCorrector
{
    /**
     * Correct an amount by a series of monthly inflation percentages.
     *
     * @param float        $amount       nominal amount
     * @param list<float>  $monthlyPcts  monthly inflation rates in percent
     *
     * @return array{corrected: float, factor: float, accumulated_pct: float}
     */
    public function correct(float $amount, array $monthlyPcts): array
    {
        $factor = 1.0;
        foreach ($monthlyPcts as $pct) {
            $factor *= (1 + $pct / 100.0);
        }

        return [
            'corrected' => round($amount * $factor, 2),
            'factor' => round($factor, 6),
            'accumulated_pct' => round(($factor - 1) * 100.0, 4),
        ];
    }
}
