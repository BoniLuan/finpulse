<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Bacen;

use FinPulse\Application\Port\HistoricalIndicatorDataProvider;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;
use FinPulse\Infrastructure\Cache\RedisCache;
use GuzzleHttp\ClientInterface;

/** Fetches current and historical BACEN SGS data, cached in Redis. */
final class BacenClient implements IndicatorDataProvider, HistoricalIndicatorDataProvider
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
            fn (): array => $this->fetchLatest($indicator, $lastN),
        );

        return new IndicatorSeries($indicator, $points);
    }

    public function history(
        Indicator $indicator,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): IndicatorSeries {
        $key = sprintf(
            'bacen:%d:range:%s:%s',
            $indicator->seriesCode(),
            $from->format('Ymd'),
            $to->format('Ymd'),
        );
        $points = $this->cache->remember($key, $this->cacheTtl, function () use ($indicator, $from, $to): array {
            $url = sprintf(
                '%s/dados/serie/bcdata.sgs.%d/dados',
                rtrim($this->baseUrl, '/'),
                $indicator->seriesCode(),
            );
            $response = $this->http->request('GET', $url, [
                'query' => [
                    'formato' => 'json',
                    'dataInicial' => $from->format('d/m/Y'),
                    'dataFinal' => $to->format('d/m/Y'),
                ],
                'timeout' => 15,
            ]);
            $raw = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return $this->normalize(is_array($raw) ? $raw : []);
        });

        return new IndicatorSeries($indicator, $points);
    }

    /** @return list<array{date: string, value: float}> */
    private function fetchLatest(Indicator $indicator, int $lastN): array
    {
        $url = sprintf(
            '%s/dados/serie/bcdata.sgs.%d/dados/ultimos/%d?formato=json',
            rtrim($this->baseUrl, '/'),
            $indicator->seriesCode(),
            $lastN,
        );
        $response = $this->http->request('GET', $url, ['timeout' => 10]);
        $raw = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $this->normalize(is_array($raw) ? $raw : []);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array{date: string, value: float}>
     */
    private function normalize(array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): array => [
                'date' => (string) $row['data'],
                'value' => (float) str_replace(',', '.', (string) $row['valor']),
            ],
            $rows,
        ));
    }
}
