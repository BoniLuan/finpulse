<?php

declare(strict_types=1);

namespace FinPulse\Application\Indicator;

use FinPulse\Application\Port\CryptoPriceProvider;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Domain\Finance\Indicator;

/**
 * Returns the latest value of every supported indicator. One failing series
 * yields a null value instead of breaking the whole list.
 */
final class ListIndicators
{
    public function __construct(
        private readonly IndicatorDataProvider $data,
        private readonly ?CryptoPriceProvider $crypto = null,
    ) {
    }

    /** @return list<array{key: string, label: string, value: float|null, series: int|null, source?: string}> */
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

        if ($this->crypto !== null) {
            try {
                $prices = $this->crypto->prices();
            } catch (\Throwable) {
                $prices = [];
            }
            foreach (['btc' => 'Bitcoin', 'eth' => 'Ethereum'] as $key => $label) {
                $out[] = [
                    'key' => $key,
                    'label' => $label,
                    'value' => $prices[$key] ?? null,
                    'series' => null,
                    'source' => 'Coinbase spot price',
                ];
            }
        }

        return $out;
    }
}
