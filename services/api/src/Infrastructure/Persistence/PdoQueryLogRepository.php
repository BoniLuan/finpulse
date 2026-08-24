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

    /**
     * @param array<string, mixed>       $data
     * @param list<array<string, mixed>> $sources
     */
    public function log(
        string $question,
        string $intentType,
        array $data,
        string $answer,
        array $sources,
        ?string $userId,
    ): string
    {
        $id = Uuid::uuid4()->toString();
        if ($userId === null) {
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO query_logs (id, user_id, question, intent_type, data, answer, sources)
             VALUES (:id, :user_id, :question, :intent_type, :data, :answer, :sources)',
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'question' => $question,
            'intent_type' => $intentType,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'answer' => $answer,
            'sources' => json_encode($sources, JSON_THROW_ON_ERROR),
        ]);

        return $id;
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, question, answer, sources, created_at
             FROM query_logs
             WHERE user_id = :user_id AND answer IS NOT NULL
             ORDER BY created_at DESC',
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map(static function (array $row): array {
            $sources = json_decode((string) $row['sources'], true, 512, JSON_THROW_ON_ERROR);

            return [
                'id' => (string) $row['id'],
                'question' => (string) $row['question'],
                'answer' => (string) $row['answer'],
                'sources' => is_array($sources) ? array_values($sources) : [],
                'created_at' => (string) $row['created_at'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function deleteForUser(string $id, string $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM query_logs WHERE id = :id AND user_id = :user_id',
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }
}
