<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Notification;

use FinPulse\Application\Port\AlertThrottle;
use Predis\Client;

/** Redis-backed cooldown: a sent alert is muted for $ttl seconds. */
final class RedisAlertThrottle implements AlertThrottle
{
    public function __construct(
        private readonly Client $redis,
        private readonly int $ttl,
    ) {
    }

    public function shouldSend(string $alertId): bool
    {
        return !(bool) $this->redis->exists($this->key($alertId));
    }

    public function markSent(string $alertId): void
    {
        $this->redis->setex($this->key($alertId), $this->ttl, '1');
    }

    private function key(string $alertId): string
    {
        return 'alert:cooldown:' . $alertId;
    }
}
