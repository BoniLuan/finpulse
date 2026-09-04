<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

use FinPulse\Domain\Finance\Indicator;

interface IndicatorHistoryRepository
{
    /** @param list<array{date: string, value: float}> $points */
    public function upsert(Indicator $indicator, array $points): int;

    public function latestDate(Indicator $indicator): ?\DateTimeImmutable;

    /** @return list<array{date: string, value: float}> */
    public function between(Indicator $indicator, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
