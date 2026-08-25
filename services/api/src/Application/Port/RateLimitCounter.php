<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

interface RateLimitCounter
{
    public function incrementWithWindow(string $key, int $window): int;
}
