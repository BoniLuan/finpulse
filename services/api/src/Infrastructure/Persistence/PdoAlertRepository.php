<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Persistence;

use FinPulse\Domain\Alert\Alert;
use FinPulse\Domain\Alert\AlertRepository;
use FinPulse\Domain\Finance\Indicator;
use PDO;

final class PdoAlertRepository implements AlertRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Alert $alert): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alerts (id, user_id, indicator, operator, threshold, channel)
             VALUES (:id, :user_id, :indicator, :operator, :threshold, :channel)',
        );
        $stmt->execute([
            'id' => $alert->id,
            'user_id' => $alert->userId,
            'indicator' => $alert->indicator->value,
            'operator' => $alert->operator,
            'threshold' => $alert->threshold,
            'channel' => $alert->channel,
        ]);
    }

    /** @return list<Alert> */
    public function all(): array
    {
        $rows = $this->pdo
            ->query('SELECT id, user_id, indicator, operator, threshold, channel FROM alerts')
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $r): Alert => new Alert(
                $r['id'],
                $r['user_id'],
                Indicator::from($r['indicator']),
                $r['operator'],
                (float) $r['threshold'],
                $r['channel'],
            ),
            $rows,
        );
    }
}
