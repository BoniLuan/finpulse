<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Market;

use FinPulse\Application\Port\CryptoPriceProvider;
use FinPulse\Infrastructure\Cache\RedisCache;
use GuzzleHttp\ClientInterface;

final class CoinbasePriceClient implements CryptoPriceProvider
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RedisCache $cache,
        private readonly string $baseUrl,
        private readonly int $cacheTtl,
    ) {
    }

    public function prices(): array
    {
        /** @var array<string, float|int|string> $prices */
        $prices = $this->cache->remember('coinbase:spot:brl', $this->cacheTtl, function (): array {
            $result = [];
            foreach (['btc' => 'BTC-BRL', 'eth' => 'ETH-BRL'] as $key => $pair) {
                $response = $this->http->request('GET', sprintf(
                    '%s/v2/prices/%s/spot',
                    rtrim($this->baseUrl, '/'),
                    $pair,
                ), ['timeout' => 10]);
                $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
                    throw new \RuntimeException('Coinbase returned an invalid price response.');
                }
                $result[$key] = (float) ($payload['data']['amount'] ?? 0);
            }

            return $result;
        });

        return array_map(static fn (float|int|string $value): float => (float) $value, $prices);
    }
}
