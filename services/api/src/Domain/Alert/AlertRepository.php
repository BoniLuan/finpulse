<?php

declare(strict_types=1);

namespace FinPulse\Domain\Alert;

interface AlertRepository
{
    public function save(Alert $alert): void;

    /** @return list<Alert> */
    public function all(): array;
}
