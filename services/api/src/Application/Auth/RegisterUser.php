<?php

declare(strict_types=1);

namespace FinPulse\Application\Auth;

use FinPulse\Domain\User\User;
use FinPulse\Domain\User\UserRepository;
use Ramsey\Uuid\Uuid;

final class RegisterUser
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function handle(string $email, string $password): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('invalid email');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('password must be at least 8 characters');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new \RuntimeException('email already registered');
        }

        $user = new User(
            Uuid::uuid4()->toString(),
            $email,
            password_hash($password, PASSWORD_DEFAULT),
        );
        $this->users->save($user);

        return $user->id;
    }
}
