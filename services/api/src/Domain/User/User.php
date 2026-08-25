<?php

declare(strict_types=1);

namespace FinPulse\Domain\User;

final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $displayName = null,
        public readonly ?string $phone = null,
    ) {
    }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->passwordHash);
    }
}
