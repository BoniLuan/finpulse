<?php

declare(strict_types=1);

namespace FinPulse\Application\Indicator;

use FinPulse\Application\Port\HistoricalIndicatorDataProvider;
use FinPulse\Application\Port\IndicatorHistoryRepository;
use FinPulse\Domain\Finance\Indicator;

final class CollectIndicatorHistory
{
    public function __construct(
        private readonly HistoricalIndicatorDataProvider $data,
        private readonly IndicatorHistoryRepository $history,
    ) {
    }

    /** @return array<string, int> */
    public function handle(): array
    {
        $processed = [];
        $to = new \DateTimeImmutable('today');
        foreach ([Indicator::SELIC, Indicator::IPCA] as $indicator) {
            $latest = $this->history->latestDate($indicator);
            $from = $latest?->modify($indicator === Indicator::SELIC ? '-7 days' : '-2 months')
                ?? $to->modify('-25 months');
            $processed[$indicator->value] = $this->history->upsert(
                $indicator,
                $this->data->history($indicator, $from, $to)->points(),
            );
        }

        return $processed;
    }
}
