<?php

declare(strict_types=1);

namespace FinPulse\Domain\Finance;

final class MacroComparisonCalculator
{
    /**
     * @param list<array{date: string, value: float}> $selic
     * @param list<array{date: string, value: float}> $ipca
     * @return array{selic_start_pct: float|null, selic_latest_pct: float|null, selic_change_pp: float|null, ipca_accumulated_pct: float|null, ipca_12m_pct: float|null, real_rate_latest_pct: float|null, monthly_correlation: float|null}
     */
    public function compare(array $selic, array $ipca): array
    {
        $monthlySelic = $this->monthlyAverages($selic);
        $monthlyIpca = $this->monthlyAverages($ipca);
        $selicValues = array_values($monthlySelic);
        $ipcaValues = array_values($monthlyIpca);
        $selicStart = $selicValues[0] ?? null;
        $selicLatest = $selicValues === [] ? null : $selicValues[count($selicValues) - 1];
        $ipcaAccumulated = $this->compound($ipcaValues);
        $ipca12m = $this->compound(array_slice($ipcaValues, -12));
        $realRate = $selicLatest === null || $ipca12m === null
            ? null
            : ((1 + $selicLatest / 100) / (1 + $ipca12m / 100) - 1) * 100;
        $sharedMonths = array_values(array_intersect(array_keys($monthlySelic), array_keys($monthlyIpca)));

        return [
            'selic_start_pct' => $this->rounded($selicStart),
            'selic_latest_pct' => $this->rounded($selicLatest),
            'selic_change_pp' => $selicStart === null || $selicLatest === null
                ? null
                : round($selicLatest - $selicStart, 4),
            'ipca_accumulated_pct' => $this->rounded($ipcaAccumulated),
            'ipca_12m_pct' => $this->rounded($ipca12m),
            'real_rate_latest_pct' => $this->rounded($realRate),
            'monthly_correlation' => $this->correlation(
                array_map(static fn (string $month): float => $monthlySelic[$month], $sharedMonths),
                array_map(static fn (string $month): float => $monthlyIpca[$month], $sharedMonths),
            ),
        ];
    }

    /**
     * @param list<array{date: string, value: float}> $points
     * @return array<string, float>
     */
    private function monthlyAverages(array $points): array
    {
        $months = [];
        foreach ($points as $point) {
            $month = substr($point['date'], 0, 7);
            $months[$month][] = $point['value'];
        }
        ksort($months);

        return array_map(static fn (array $values): float => array_sum($values) / count($values), $months);
    }

    /** @param list<float> $values */
    private function compound(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        $factor = 1.0;
        foreach ($values as $value) {
            $factor *= 1 + $value / 100;
        }

        return ($factor - 1) * 100;
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function correlation(array $left, array $right): ?float
    {
        $count = count($left);
        if ($count < 2 || $count !== count($right)) {
            return null;
        }
        $leftMean = array_sum($left) / $count;
        $rightMean = array_sum($right) / $count;
        $numerator = $leftVariance = $rightVariance = 0.0;
        for ($index = 0; $index < $count; ++$index) {
            $leftDelta = $left[$index] - $leftMean;
            $rightDelta = $right[$index] - $rightMean;
            $numerator += $leftDelta * $rightDelta;
            $leftVariance += $leftDelta ** 2;
            $rightVariance += $rightDelta ** 2;
        }
        $denominator = sqrt($leftVariance * $rightVariance);

        return $denominator === 0.0 ? null : round($numerator / $denominator, 4);
    }

    private function rounded(?float $value): ?float
    {
        return $value === null ? null : round($value, 4);
    }
}
