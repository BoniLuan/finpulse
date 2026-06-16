<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Bacen;

use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;
use FinPulse\Infrastructure\Cache\RedisCache;
use GuzzleHttp\ClientInterface;

/**
 * Fetches indicator data from BACEN's free SGS API, cached in Redis.
 *
 *   GET {base}/dados/serie/bcdata.sgs.{code}/dados?formato=json&ultimos=N
 *   → [{ "data": "dd/mm/yyyy", "valor": "1.23" }, ...]
 */
final class BacenClient implements IndicatorDataProvider
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RedisCache $cache,
        private readonly string $baseUrl,
        private readonly int $cacheTtl,
    ) {
    }

    public function latest(Indicator $indicator): float
    {
        return $this->series($indicator, 1)->latest();
    }

    public function series(Indicator $indicator, int $lastN): IndicatorSeries
    {
        $lastN = max(1, $lastN);
        $key = sprintf('bacen:%d:last:%d', $indicator->seriesCode(), $lastN);

        $points = $this->cache->remember(
            $key,
            $this->cacheTtl,
            fn (): array => $this->fetch($indicator, $lastN),
        );

        return new IndicatorSeries($indicator, $points);
    }

    /** @return array<int, array{date: string, value: float}> */
    private function fetch(Indicator $indicator, int $lastN): array
    {
        $url = sprintf(
            '%s/dados/serie/bcdata.sgs.%d/dados/ultimos/%d?formato=json',
            rtrim($this->baseUrl, '/'),
            $indicator->seriesCode(),
            $lastN,
        );

        $response = $this->http->request('GET', $url, ['timeout' => 10]);
        $raw = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            static fn (array $row): array => [
                'date' => (string) $row['data'],
                'value' => (float) str_replace(',', '.', (string) $row['valor']),
            ],
            is_array($raw) ? $raw : [],
        );
    }
}
