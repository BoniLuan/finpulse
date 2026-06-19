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

        return $this->hydrate($rows);
    }

    /** @return list<Alert> */
    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, indicator, operator, threshold, channel
             FROM alerts WHERE user_id = :uid ORDER BY created_at DESC',
        );
        $stmt->execute(['uid' => $userId]);

        return $this->hydrate($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function deleteForUser(string $id, string $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM alerts WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<Alert>
     */
    private function hydrate(array $rows): array
    {
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
