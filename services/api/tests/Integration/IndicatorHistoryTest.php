<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Indicator\CollectIndicatorHistory;
use FinPulse\Application\Indicator\CompareSelicIpca;
use FinPulse\Application\Indicator\ListIndicatorHistory;
use FinPulse\Application\Port\HistoricalIndicatorDataProvider;
use FinPulse\Application\Port\IndicatorHistoryRepository;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;
use FinPulse\Domain\Finance\MacroComparisonCalculator;
use FinPulse\Http\Action\IndicatorHistoryAction;
use FinPulse\Http\Action\SelicIpcaComparisonAction;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class IndicatorHistoryTest extends TestCase
{
    public function testCollectorPersistsSelicAndIpcaIdempotentlyThroughRepository(): void
    {
        $repository = new InMemoryIndicatorHistoryRepository();
        $result = (new CollectIndicatorHistory(new HistoricalDataProvider(), $repository))->handle();

        self::assertSame(['selic' => 2, 'ipca' => 2], $result);
        self::assertSame(2, $repository->requested[Indicator::SELIC->value]);
        self::assertSame(2, $repository->requested[Indicator::IPCA->value]);
    }

    public function testListsAValidatedHistoricalPeriod(): void
    {
        $repository = new InMemoryIndicatorHistoryRepository();
        $repository->rows = [['date' => '2026-08-01', 'value' => 15.0]];

        $result = (new ListIndicatorHistory($repository))->handle('selic', 24);

        self::assertSame('selic', $result['indicator']['key']);
        self::assertSame(432, $result['indicator']['series']);
        self::assertSame($repository->rows, $result['observations']);
        self::assertSame('selic', $repository->lastIndicator?->value);
    }

    public function testHistoryActionReturnsThePublicJsonContract(): void
    {
        $repository = new InMemoryIndicatorHistoryRepository();
        $repository->rows = [['date' => '2026-08-01', 'value' => 15.0]];
        $action = new IndicatorHistoryAction(new ListIndicatorHistory($repository));
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/indicators/selic/observations?months=24');
        $response = $action($request, (new ResponseFactory())->createResponse(), ['key' => 'selic']);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('selic', $payload['indicator']['key']);
        self::assertEquals($repository->rows, $payload['observations']);
    }

    public function testComparisonActionReturnsSeriesAndAnalytics(): void
    {
        $repository = new InMemoryIndicatorHistoryRepository();
        $repository->rowsByIndicator = [
            'selic' => [['date' => '2026-08-01', 'value' => 14.0]],
            'ipca' => [['date' => '2026-08-01', 'value' => 0.5]],
        ];
        $action = new SelicIpcaComparisonAction(
            new CompareSelicIpca($repository, new MacroComparisonCalculator()),
        );
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/v1/comparisons/selic-ipca?months=24');
        $response = $action($request, (new ResponseFactory())->createResponse());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(24, $payload['period']['months']);
        self::assertCount(2, $payload['series']);
        self::assertSame(14, $payload['summary']['selic_latest_pct']);
    }

    public function testRejectsUnsupportedHistoryIndicator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ListIndicatorHistory(new InMemoryIndicatorHistoryRepository()))->handle('btc', 24);
    }
}

final class HistoricalDataProvider implements HistoricalIndicatorDataProvider
{
    public function history(
        Indicator $indicator,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): IndicatorSeries {
        return new IndicatorSeries($indicator, [
            ['date' => '01/07/2026', 'value' => 14.75],
            ['date' => '01/08/2026', 'value' => 15.0],
        ]);
    }
}

final class InMemoryIndicatorHistoryRepository implements IndicatorHistoryRepository
{
    /** @var list<array{date: string, value: float}> */
    public array $rows = [];

    /** @var array<string, list<array{date: string, value: float}>> */
    public array $rowsByIndicator = [];

    /** @var array<string, int> */
    public array $requested = [];

    public ?Indicator $lastIndicator = null;

    public ?\DateTimeImmutable $latest = null;

    public function latestDate(Indicator $indicator): ?\DateTimeImmutable
    {
        return $this->latest;
    }

    public function upsert(Indicator $indicator, array $points): int
    {
        $this->requested[$indicator->value] = count($points);

        return count($points);
    }

    public function between(Indicator $indicator, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $this->lastIndicator = $indicator;

        return $this->rowsByIndicator[$indicator->value] ?? $this->rows;
    }
}
