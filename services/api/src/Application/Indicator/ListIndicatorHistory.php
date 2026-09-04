<?php

declare(strict_types=1);

namespace FinPulse\Application\Indicator;

use FinPulse\Application\Port\IndicatorHistoryRepository;
use FinPulse\Domain\Finance\Indicator;

final class ListIndicatorHistory
{
    public function __construct(private readonly IndicatorHistoryRepository $history)
    {
    }

    /** @return array{indicator: array<string, int|string>, period: array<string, string>, observations: list<array{date: string, value: float}>} */
    public function handle(string $key, int $months = 24): array
    {
        $indicator = Indicator::fromName($key);
        if ($indicator === null || !in_array($indicator, [Indicator::SELIC, Indicator::IPCA], true)) {
            throw new \InvalidArgumentException('Historical data is available for selic and ipca.');
        }
        if ($months < 1 || $months > 120) {
            throw new \InvalidArgumentException('months must be between 1 and 120.');
        }

        $to = new \DateTimeImmutable('today');
        $from = $to->modify(sprintf('-%d months', $months));

        return [
            'indicator' => [
                'key' => $indicator->value,
                'label' => $indicator->label(),
                'series' => $indicator->seriesCode(),
            ],
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'observations' => $this->history->between($indicator, $from, $to),
        ];
    }
}
