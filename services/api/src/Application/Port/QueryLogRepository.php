<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

/** Persists a record of each answered question (for observability/analytics). */
interface QueryLogRepository
{
    /** @param array<string, mixed> $data */
    public function log(string $question, string $intentType, array $data): void;
}
