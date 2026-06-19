<?php

declare(strict_types=1);

namespace FinPulse\Domain\Alert;

interface AlertRepository
{
    public function save(Alert $alert): void;

    /** @return list<Alert> */
    public function all(): array;

    /** @return list<Alert> */
    public function findByUser(string $userId): array;

    /** Delete an alert only if it belongs to the user. Returns true if a row was removed. */
    public function deleteForUser(string $id, string $userId): bool;
}
