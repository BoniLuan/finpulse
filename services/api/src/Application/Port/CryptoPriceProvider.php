<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

interface CryptoPriceProvider
{
    /** @return array<string, float> asset key to BRL spot price */
    public function prices(): array;
}
