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

    public function handle(string $email, string $password, string $displayName = '', string $phone = ''): string
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

        $displayName = trim($displayName);
        if (strlen($displayName) < 2 || strlen($displayName) > 60) {
            throw new \InvalidArgumentException('name must be between 2 and 60 characters');
        }
        $phone = self::normalizePhone($phone);

        $user = new User(
            Uuid::uuid4()->toString(),
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $displayName,
            $phone,
        );
        $this->users->save($user);

        return $user->id;
    }

    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new \InvalidArgumentException('phone must include country code and contain 10 to 15 digits');
        }

        return $digits;
    }
}
