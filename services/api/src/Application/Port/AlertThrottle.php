<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

/**
 * Prevents a triggered alert from re-firing on every scheduler cycle. After an
 * alert is sent, it is muted for a cooldown window.
 */
interface AlertThrottle
{
    public function shouldSend(string $alertId): bool;

    public function markSent(string $alertId): void;
}
