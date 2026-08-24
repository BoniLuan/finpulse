<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

/** Persists answered questions and exposes user-scoped conversation history. */
interface QueryLogRepository
{
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
    ): string;

    /** @return list<array{id: string, question: string, answer: string, sources: list<mixed>, created_at: string}> */
    public function findByUser(string $userId): array;

    public function deleteForUser(string $id, string $userId): bool;
}
