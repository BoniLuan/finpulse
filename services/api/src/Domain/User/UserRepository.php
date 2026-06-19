<?php

declare(strict_types=1);

namespace FinPulse\Domain\User;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    public function save(User $user): void;
}
