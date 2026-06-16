<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Ask\AskQuestion;
use FinPulse\Application\Ask\Intent;
use FinPulse\Application\Port\AnswerWriter;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\IntentParser;
use FinPulse\Application\Port\QueryLogRepository;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;
use FinPulse\Domain\Finance\InflationCorrector;
use FinPulse\Domain\Finance\InvestmentCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Wires the AskQuestion use case with fakes for every port (AI worker, BACEN,
 * persistence) to exercise the full orchestration without IO.
 */
final class AskQuestionTest extends TestCase
{
    public function testInvestmentReturnFlow(): void
    {
        $intent = new Intent(Intent::INVESTMENT_RETURN, [
            'principal' => 1000,
            'months' => 12,
            'indicator' => 'poupanca',
        ]);

        $askQuestion = new AskQuestion(
            new FakeIntentParser($intent),
            new FakeDataProvider(['poupanca' => 0.5]),
            new FakeAnswerWriter(),
            new FakeQueryLog(),
            new InvestmentCalculator(),
            new InflationCorrector(),
        );

        $result = $askQuestion->handle('quanto rende 1000 na poupanca em 12 meses?');

        self::assertSame(Intent::INVESTMENT_RETURN, $result->data['type']);
        // 1000 at 0.5%/mo for 12 months ≈ 1061.68
        self::assertEqualsWithDelta(1061.68, $result->data['result'], 0.01);
        self::assertStringContainsString('answer', $result->answer);
        self::assertSame('BACEN SGS', $result->sources[0]['name']);
    }
}

final class FakeIntentParser implements IntentParser
{
    public function __construct(private readonly Intent $intent)
    {
    }

    public function parse(string $question): Intent
    {
        return $this->intent;
    }
}

final class FakeDataProvider implements IndicatorDataProvider
{
    /** @param array<string, float> $latest */
    public function __construct(private readonly array $latest)
    {
    }

    public function latest(Indicator $indicator): float
    {
        return $this->latest[$indicator->value] ?? 0.0;
    }

    public function series(Indicator $indicator, int $lastN): IndicatorSeries
    {
        return new IndicatorSeries($indicator, [['date' => '01/01/2025', 'value' => $this->latest($indicator)]]);
    }
}

final class FakeAnswerWriter implements AnswerWriter
{
    /** @param array<string, mixed> $result */
    public function write(Intent $intent, array $result): string
    {
        return 'answer: ' . json_encode($result);
    }
}

final class FakeQueryLog implements QueryLogRepository
{
    /** @param array<string, mixed> $data */
    public function log(string $question, string $intentType, array $data): void
    {
        // no-op
    }
}
