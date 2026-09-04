<?php

declare(strict_types=1);

namespace FinPulse\Application\Indicator;

use FinPulse\Application\Port\IndicatorHistoryRepository;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\MacroComparisonCalculator;

final class CompareSelicIpca
{
    public function __construct(
        private readonly IndicatorHistoryRepository $history,
        private readonly MacroComparisonCalculator $calculator,
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(int $months = 24): array
    {
        if ($months < 1 || $months > 120) {
            throw new \InvalidArgumentException('months must be between 1 and 120.');
        }
        $to = new \DateTimeImmutable('today');
        $from = $to->modify(sprintf('-%d months', $months));
        $selic = $this->history->between(Indicator::SELIC, $from, $to);
        $ipca = $this->history->between(Indicator::IPCA, $from, $to);

        return [
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'months' => $months],
            'series' => [
                ['indicator' => ['key' => 'selic', 'label' => Indicator::SELIC->label(), 'series' => 432], 'observations' => $selic],
                ['indicator' => ['key' => 'ipca', 'label' => Indicator::IPCA->label(), 'series' => 433], 'observations' => $ipca],
            ],
            'summary' => $this->calculator->compare($selic, $ipca),
        ];
    }
}
