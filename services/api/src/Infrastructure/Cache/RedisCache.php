<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Cache;

use Predis\Client;

/** Thin cache facade over Predis with a get-or-compute helper. */
final class RedisCache
{
    public function __construct(private readonly Client $redis)
    {
    }

    /**
     * Return the cached value for $key, or compute it via $producer, store it
     * with $ttl seconds, and return it.
     *
     * @template T
     * @param callable():T $producer
     * @return T
     */
    public function remember(string $key, int $ttl, callable $producer): mixed
    {
        $cached = $this->redis->get($key);
        if ($cached !== null) {
            return json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
        }

        $value = $producer();
        $this->redis->setex($key, $ttl, json_encode($value, JSON_THROW_ON_ERROR));

        return $value;
    }

    /** Atomic increment with a window TTL — used by the rate limiter. */
    public function incrementWithWindow(string $key, int $window): int
    {
        $count = (int) $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, $window);
        }

        return $count;
    }
}
