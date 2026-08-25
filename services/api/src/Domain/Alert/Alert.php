<?php

declare(strict_types=1);

namespace FinPulse\Domain\Alert;

final class Alert
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly AlertMetric $indicator,
        public readonly string $operator, // '>' or '<'
        public readonly float $threshold,
        public readonly string $channel,  // 'log' | 'email' | 'whatsapp'
    ) {
        if (!in_array($operator, ['>', '<'], true)) {
            throw new \InvalidArgumentException('operator must be ">" or "<"');
        }
        if (!in_array($channel, ['log', 'email', 'whatsapp'], true)) {
            throw new \InvalidArgumentException('channel must be "log", "email", or "whatsapp"');
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
