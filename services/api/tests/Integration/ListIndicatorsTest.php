<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Indicator\ListIndicators;
use FinPulse\Application\Port\CryptoPriceProvider;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;
use PHPUnit\Framework\TestCase;

final class ListIndicatorsTest extends TestCase
{
    public function testReturnsEveryIndicatorWithValueAndSeries(): void
    {
        $result = (new ListIndicators(new StubProvider(14.5)))->handle();

        self::assertCount(count(Indicator::cases()), $result);
        $selic = $result[0];
        self::assertSame('selic', $selic['key']);
        self::assertSame(14.5, $selic['value']);
        self::assertSame(432, $selic['series']);
    }

    public function testFailingSeriesYieldsNullNotError(): void
    {
        $result = (new ListIndicators(new StubProvider(null)))->handle();

        self::assertNull($result[0]['value']);
    }

    public function testIncludesCryptoSpotPricesWhenProviderIsConfigured(): void
    {
        $result = (new ListIndicators(new StubProvider(14.5), new StubCryptoProvider()))->handle();

        self::assertSame('btc', $result[count(Indicator::cases())]['key']);
        self::assertSame(350000.0, $result[count(Indicator::cases())]['value']);
        self::assertNull($result[count(Indicator::cases())]['series']);
    }
}

final class StubCryptoProvider implements CryptoPriceProvider
{
    public function prices(): array
    {
        return ['btc' => 350000.0, 'eth' => 18000.0];
    }
}

final class StubProvider implements IndicatorDataProvider
{
    public function __construct(private readonly ?float $value)
    {
    }

    public function latest(Indicator $indicator): float
    {
        if ($this->value === null) {
            throw new \RuntimeException('series unavailable');
        }

        return $this->value;
    }

    public function series(Indicator $indicator, int $lastN): IndicatorSeries
    {
        return new IndicatorSeries($indicator, []);
    }
}
