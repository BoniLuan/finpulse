<?php

declare(strict_types=1);

namespace FinPulse\Application\Indicator;

use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Domain\Finance\Indicator;

/**
 * Returns the latest value of every supported indicator. One failing series
 * yields a null value instead of breaking the whole list.
 */
final class ListIndicators
{
    public function __construct(private readonly IndicatorDataProvider $data)
    {
    }

    /** @return list<array{key: string, label: string, value: float|null, series: int}> */
    public function handle(): array
    {
        $out = [];
        foreach (Indicator::cases() as $indicator) {
            try {
                $value = $this->data->latest($indicator);
            } catch (\Throwable) {
                $value = null;
            }
            $out[] = [
                'key' => $indicator->value,
                'label' => $indicator->label(),
                'value' => $value,
                'series' => $indicator->seriesCode(),
            ];
        }

        return $out;
    }
}
