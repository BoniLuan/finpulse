<?php

declare(strict_types=1);

namespace FinPulse\Domain\Alert;

use FinPulse\Domain\Finance\Indicator;

final class Alert
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly Indicator $indicator,
        public readonly string $operator, // '>' or '<'
        public readonly float $threshold,
        public readonly string $channel,  // 'log' | 'web' | (whatsapp: TODO)
    ) {
        if (!in_array($operator, ['>', '<'], true)) {
            throw new \InvalidArgumentException('operator must be ">" or "<"');
        }
    }

    /** Whether the alert condition is met for a given observed value. */
    public function isTriggeredBy(float $value): bool
    {
        return $this->operator === '>'
            ? $value > $this->threshold
            : $value < $this->threshold;
    }
}
