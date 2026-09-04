<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;

interface HistoricalIndicatorDataProvider
{
    public function history(
        Indicator $indicator,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): IndicatorSeries;
}
