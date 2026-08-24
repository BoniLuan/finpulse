<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\History\DeleteHistory;
use FinPulse\Application\History\ListHistory;
use FinPulse\Application\Port\QueryLogRepository;
use PHPUnit\Framework\TestCase;

final class ConversationHistoryTest extends TestCase
{
    public function testHistoryIsUserScopedAndCanBeDeleted(): void
    {
        $repo = new MemoryHistoryRepository();
        $repo->log('Question one', 'indicator_value', [], 'Answer one', [], 'user-1');
        $repo->log('Question two', 'indicator_value', [], 'Answer two', [], 'user-2');

        $history = (new ListHistory($repo))->handle('user-1');
        self::assertCount(1, $history);
        self::assertSame('Question one', $history[0]['question']);
        self::assertFalse((new DeleteHistory($repo))->handle($history[0]['id'], 'user-2'));
        self::assertTrue((new DeleteHistory($repo))->handle($history[0]['id'], 'user-1'));
        self::assertSame([], (new ListHistory($repo))->handle('user-1'));
    }
}

final class MemoryHistoryRepository implements QueryLogRepository
{
    /** @var array<string, array{id: string, user_id: ?string, question: string, answer: string, sources: list<mixed>, created_at: string}> */
    private array $rows = [];

    public function log(
        string $question,
        string $intentType,
        array $data,
        string $answer,
        array $sources,
        ?string $userId,
    ): string {
        $id = 'history-' . (count($this->rows) + 1);
        $this->rows[$id] = [
            'id' => $id,
            'user_id' => $userId,
            'question' => $question,
            'answer' => $answer,
            'sources' => $sources,
            'created_at' => '2026-01-01',
        ];

        return $id;
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_map(
            static fn (array $row): array => array_diff_key($row, ['user_id' => true]),
            array_filter($this->rows, static fn (array $row): bool => $row['user_id'] === $userId),
        ));
    }

    public function deleteForUser(string $id, string $userId): bool
    {
        if (($this->rows[$id]['user_id'] ?? null) !== $userId) {
            return false;
        }
        unset($this->rows[$id]);

        return true;
    }
}
