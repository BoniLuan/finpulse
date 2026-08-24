<?php

declare(strict_types=1);

namespace FinPulse\Application\History;

use FinPulse\Application\Port\QueryLogRepository;

final class DeleteHistory
{
    public function __construct(private readonly QueryLogRepository $history)
    {
    }

    public function handle(string $id, string $userId): bool
    {
        return $this->history->deleteForUser($id, $userId);
    }
}
