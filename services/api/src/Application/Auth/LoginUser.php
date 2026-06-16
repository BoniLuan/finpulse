<?php

declare(strict_types=1);

namespace FinPulse\Application\Auth;

use FinPulse\Application\Port\TokenIssuer;
use FinPulse\Domain\User\UserRepository;

final class LoginUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenIssuer $tokens,
    ) {
    }

    /** @return array{token: string, expires_in: int} */
    public function handle(string $email, string $password): array
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user === null || !$user->verifyPassword($password)) {
            throw new \RuntimeException('invalid credentials');
        }

        return $this->tokens->issue($user->id);
    }
}
