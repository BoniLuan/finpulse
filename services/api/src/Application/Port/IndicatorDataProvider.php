<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;

/** Source of indicator data (implemented by the BACEN client). */
interface IndicatorDataProvider
{
    public function latest(Indicator $indicator): float;

    /** @param int $lastN number of most recent points to fetch */
    public function series(Indicator $indicator, int $lastN): IndicatorSeries;
}
