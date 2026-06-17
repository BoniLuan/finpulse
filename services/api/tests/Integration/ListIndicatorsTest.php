<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Indicator\ListIndicators;
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
