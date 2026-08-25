<?php

declare(strict_types=1);

namespace FinPulse\Application\Auth;

use FinPulse\Domain\User\User;
use FinPulse\Domain\User\UserRepository;

final class UpdateProfile
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function handle(string $userId, string $displayName, string $phone): User
    {
        $user = $this->users->findById($userId)
            ?? throw new \RuntimeException('user not found');
        $displayName = trim($displayName);
        if (strlen($displayName) < 2 || strlen($displayName) > 60) {
            throw new \InvalidArgumentException('name must be between 2 and 60 characters');
        }

        $updated = new User(
            $user->id,
            $user->email,
            $user->passwordHash,
            $displayName,
            RegisterUser::normalizePhone($phone),
        );
        $this->users->save($updated);

        return $updated;
    }
}
