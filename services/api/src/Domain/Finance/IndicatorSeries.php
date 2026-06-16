<?php

declare(strict_types=1);

namespace FinPulse\Domain\Finance;

/**
 * An immutable time series of {date => value} points for one indicator.
 * Pure value object — no IO.
 */
final class IndicatorSeries
{
    /** @var array<int, array{date: string, value: float}> */
    private array $points;

    /** @param array<int, array{date: string, value: float}> $points */
    public function __construct(
        public readonly Indicator $indicator,
        array $points,
    ) {
        $this->points = array_values($points);
    }

    /** @return array<int, array{date: string, value: float}> */
    public function points(): array
    {
        return $this->points;
    }

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function latest(): float
    {
        if ($this->isEmpty()) {
            throw new \RuntimeException('Series has no data points.');
        }

        return $this->points[count($this->points) - 1]['value'];
    }

    /** Sum of the `value` of every point (e.g. accumulated monthly IPCA %). */
    public function sumValues(): float
    {
        return array_sum(array_column($this->points, 'value'));
    }
}
