<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Ask\AskQuestion;
use FinPulse\Application\Ask\Intent;
use FinPulse\Application\Port\AnswerWriter;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\IntentParser;
use FinPulse\Application\Port\MacroComparisonProvider;
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
            new FakeMacroComparisonProvider(),
            new FakeAnswerWriter(),
            new FakeQueryLog(),
            new InvestmentCalculator(),
            new InflationCorrector(),
        );

        $result = $askQuestion->handle('how much does 1000 in savings yield over 12 months?');

        self::assertSame(Intent::INVESTMENT_RETURN, $result->data['type']);
        // 1000 at 0.5%/mo for 12 months ≈ 1061.68
        self::assertEqualsWithDelta(1061.68, $result->data['result'], 0.01);
        self::assertStringContainsString('answer', $result->answer);
        self::assertSame('BACEN SGS', $result->sources[0]['name']);
    }

    public function testMacroComparisonUsesHistoricalAnalyticsWithoutLiveIndicatorFetch(): void
    {
        $data = new FakeDataProvider([]);
        $askQuestion = new AskQuestion(
            new FakeIntentParser(new Intent(Intent::MACRO_COMPARISON, ['months' => 24])),
            $data,
            new FakeMacroComparisonProvider(),
            new FakeAnswerWriter(),
            new FakeQueryLog(),
            new InvestmentCalculator(),
            new InflationCorrector(),
        );

        $result = $askQuestion->handle('compare Selic and IPCA over the last 24 months');

        self::assertSame(Intent::MACRO_COMPARISON, $result->data['type']);
        self::assertSame(3.4, $result->data['summary']['selic_change_pp']);
        self::assertSame(['selic' => 2, 'ipca' => 2], $result->data['observation_counts']);
        self::assertCount(2, $result->sources);
        self::assertSame(0, $data->latestCalls);
    }

    public function testGeneralQuestionDoesNotFetchAnIndicator(): void
    {
        $data = new FakeDataProvider([]);
        $askQuestion = new AskQuestion(
            new FakeIntentParser(new Intent(Intent::GENERAL)),
            $data,
            new FakeMacroComparisonProvider(),
            new FakeAnswerWriter(),
            new FakeQueryLog(),
            new InvestmentCalculator(),
            new InflationCorrector(),
        );

        $result = $askQuestion->handle('purple bicycles dance quietly');

        self::assertSame(Intent::GENERAL, $result->data['type']);
        self::assertSame('purple bicycles dance quietly', $result->data['question']);

        self::assertSame([], $result->sources);
        self::assertSame(0, $data->latestCalls);
    }

    public function testInvalidIndicatorIsLoggedAsGeneral(): void
    {
        $data = new FakeDataProvider([]);
        $log = new FakeQueryLog();
        $askQuestion = new AskQuestion(
            new FakeIntentParser(new Intent(Intent::INDICATOR_VALUE, ['indicator' => 'unknown'])),
            $data,
            new FakeMacroComparisonProvider(),
            new FakeAnswerWriter(),
            $log,
            new InvestmentCalculator(),
            new InflationCorrector(),
        );

        $result = $askQuestion->handle('tell me something');

        self::assertSame(Intent::GENERAL, $result->data['type']);
        self::assertSame(Intent::GENERAL, $log->lastIntentType);
        self::assertSame(0, $data->latestCalls);
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
    public int $latestCalls = 0;

    /** @param array<string, float> $latest */
    public function __construct(private readonly array $latest)
    {
    }

    public function latest(Indicator $indicator): float
    {
        ++$this->latestCalls;
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
    public ?string $lastIntentType = null;

    public function log(
        string $question,
        string $intentType,
        array $data,
        string $answer,
        array $sources,
        ?string $userId,
    ): string {
        $this->lastIntentType = $intentType;
        return 'query-1';
    }

    public function findByUser(string $userId): array
    {
        return [];
    }

    public function deleteForUser(string $id, string $userId): bool
    {
        return false;
    }
}

final class FakeMacroComparisonProvider implements MacroComparisonProvider
{
    public function compare(int $months): array
    {
        $series = static fn (string $key): array => [
            'indicator' => ['key' => $key],
            'observations' => [
                ['date' => '2026-07-01', 'value' => 1.0],
                ['date' => '2026-08-01', 'value' => 2.0],
            ],
        ];

        return [
            'period' => ['months' => $months, 'from' => '2024-08-01', 'to' => '2026-08-01'],
            'series' => [$series('selic'), $series('ipca')],
            'summary' => ['selic_change_pp' => 3.4],
        ];
    }
}
