<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Persistence;

use FinPulse\Application\Port\QueryLogRepository;
use PDO;
use Ramsey\Uuid\Uuid;

final class PdoQueryLogRepository implements QueryLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    public function log(string $question, string $intentType, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO query_logs (id, question, intent_type, data)
             VALUES (:id, :question, :intent_type, :data)',
        );
        $stmt->execute([
            'id' => Uuid::uuid4()->toString(),
            'question' => $question,
            'intent_type' => $intentType,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
    }
}
