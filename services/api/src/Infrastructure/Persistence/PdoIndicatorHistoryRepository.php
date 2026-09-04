<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Persistence;

use FinPulse\Application\Port\IndicatorHistoryRepository;
use FinPulse\Domain\Finance\Indicator;

final class PdoIndicatorHistoryRepository implements IndicatorHistoryRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function latestDate(Indicator $indicator): ?\DateTimeImmutable
    {
        $statement = $this->pdo->prepare(
            'SELECT max(observed_on) FROM indicator_observations WHERE indicator_key = :indicator',
        );
        $statement->execute(['indicator' => $indicator->value]);
        $date = $statement->fetchColumn();

        return is_string($date) ? new \DateTimeImmutable($date) : null;
    }

    public function upsert(Indicator $indicator, array $points): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO indicator_observations (indicator_key, observed_on, value)
             VALUES (:indicator, :observed_on, :value)
             ON CONFLICT (indicator_key, observed_on) DO UPDATE
             SET value = EXCLUDED.value, collected_at = now()',
        );
        $written = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($points as $point) {
                $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $point['date']);
                if ($date === false) {
                    continue;
                }
                $statement->execute([
                    'indicator' => $indicator->value,
                    'observed_on' => $date->format('Y-m-d'),
                    'value' => $point['value'],
                ]);
                ++$written;
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $written;
    }

    public function between(Indicator $indicator, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT observed_on, value FROM indicator_observations
             WHERE indicator_key = :indicator AND observed_on BETWEEN :from AND :to
             ORDER BY observed_on ASC',
        );
        $statement->execute([
            'indicator' => $indicator->value,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        return array_map(
            static fn (array $row): array => [
                'date' => (string) $row['observed_on'],
                'value' => (float) $row['value'],
            ],
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }
}
