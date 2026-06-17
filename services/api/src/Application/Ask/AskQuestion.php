<?php

declare(strict_types=1);

namespace FinPulse\Application\Ask;

use FinPulse\Application\Port\AnswerWriter;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\IntentParser;
use FinPulse\Application\Port\QueryLogRepository;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\InflationCorrector;
use FinPulse\Domain\Finance\InvestmentCalculator;

/**
 * Core use case: answer a finance question end-to-end.
 *
 *   question → parse intent → fetch BACEN data → compute → write answer → log
 *
 * Orchestration only: all IO is behind ports, all math is in Domain.
 */
final class AskQuestion
{
    public function __construct(
        private readonly IntentParser $intentParser,
        private readonly IndicatorDataProvider $data,
        private readonly AnswerWriter $answerWriter,
        private readonly QueryLogRepository $queryLog,
        private readonly InvestmentCalculator $investment,
        private readonly InflationCorrector $inflation,
    ) {
    }

    public function handle(string $question): AskResult
    {
        $intent = $this->intentParser->parse($question);

        [$result, $sources] = match ($intent->type) {
            Intent::INVESTMENT_RETURN => $this->investmentReturn($intent),
            Intent::INFLATION_CORRECTION => $this->inflationCorrection($intent),
            default => $this->indicatorValue($intent),
        };

        $answer = $this->answerWriter->write($intent, $result);
        $this->queryLog->log($question, $intent->type, $result);

        return new AskResult($answer, $result, $sources);
    }

    /** @return array{0: array<string,mixed>, 1: list<array<string,mixed>>} */
    private function indicatorValue(Intent $intent): array
    {
        $indicator = $this->resolveIndicator($intent->param('indicator', 'selic'));
        $value = $this->data->latest($indicator);

        return [
            ['type' => Intent::INDICATOR_VALUE, 'indicator' => $indicator->value, 'value' => $value],
            [$this->source($indicator)],
        ];
    }

    /** @return array{0: array<string,mixed>, 1: list<array<string,mixed>>} */
    private function investmentReturn(Intent $intent): array
    {
        $principal = (float) $intent->param('principal', 0);
        $months = (int) $intent->param('months', 12);
        $indicator = $this->resolveIndicator($intent->param('indicator', 'poupanca'));
        $percentOfCdi = (float) $intent->param('percent_of_cdi', 100);

        $rate = $this->data->latest($indicator);

        // Pick the right product math per indicator.
        [$future, $monthlyPct] = match ($indicator) {
            Indicator::POUPANCA => [
                // savings series is already a monthly rate
                $this->investment->futureValue($principal, $rate, $months),
                $rate,
            ],
            Indicator::CDI => [
                $this->investment->cdbReturn($principal, $rate, $percentOfCdi, $months),
                $this->investment->annualToMonthlyPct($rate * $percentOfCdi / 100),
            ],
            default => [
                $this->investment->treasurySelicReturn($principal, $rate, $months),
                $this->investment->annualToMonthlyPct($rate),
            ],
        };

        $data = [
            'type' => Intent::INVESTMENT_RETURN,
            'principal' => $principal,
            'months' => $months,
            'indicator' => $indicator->value,
            'monthly_rate_pct' => round($monthlyPct, 4),
            'result' => $future,
            'earnings' => round($future - $principal, 2),
        ];
        if ($indicator === Indicator::CDI) {
            $data['percent_of_cdi'] = $percentOfCdi;
        }

        return [$data, [$this->source($indicator)]];
    }

    /** @return array{0: array<string,mixed>, 1: list<array<string,mixed>>} */
    private function inflationCorrection(Intent $intent): array
    {
        $amount = (float) $intent->param('amount', 0);
        $months = (int) $intent->param('months', 12);

        $series = $this->data->series(Indicator::IPCA, $months);
        $monthly = array_map(
            static fn (array $p): float => $p['value'],
            $series->points(),
        );
        $correction = $this->inflation->correct($amount, $monthly);

        return [
            ['type' => Intent::INFLATION_CORRECTION, 'amount' => $amount, 'months' => $months] + $correction,
            [$this->source(Indicator::IPCA)],
        ];
    }

    private function resolveIndicator(mixed $name): Indicator
    {
        return Indicator::fromName((string) $name) ?? Indicator::SELIC;
    }

    /** @return array<string, mixed> */
    private function source(Indicator $indicator): array
    {
        return [
            'name' => 'BACEN SGS',
            'series' => $indicator->seriesCode(),
            'label' => $indicator->label(),
        ];
    }
}
