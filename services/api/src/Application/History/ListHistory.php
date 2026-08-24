<?php

declare(strict_types=1);

namespace FinPulse\Application\History;

use FinPulse\Application\Port\QueryLogRepository;

final class ListHistory
{
    public function __construct(private readonly QueryLogRepository $history)
    {
    }

    /** @return list<array{id: string, question: string, answer: string, sources: list<mixed>, created_at: string}> */
    public function handle(string $userId): array
    {
        return $this->history->findByUser($userId);
    }
}
