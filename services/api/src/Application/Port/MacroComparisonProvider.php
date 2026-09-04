<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

interface MacroComparisonProvider
{
    /** @return array<string, mixed> */
    public function compare(int $months): array;
}
