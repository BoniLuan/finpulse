<?php

declare(strict_types=1);

namespace FinPulse\Application\Alert;

use FinPulse\Domain\Alert\AlertRepository;

final class DeleteAlert
{
    public function __construct(private readonly AlertRepository $alerts)
    {
    }

    /** @return bool true if an alert owned by the user was deleted */
    public function handle(string $id, string $userId): bool
    {
        return $this->alerts->deleteForUser($id, $userId);
    }
}
