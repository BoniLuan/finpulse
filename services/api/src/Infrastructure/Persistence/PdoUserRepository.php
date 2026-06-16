<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Persistence;

use FinPulse\Domain\User\User;
use FinPulse\Domain\User\UserRepository;
use PDO;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash FROM users WHERE email = :email',
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new User($row['id'], $row['email'], $row['password_hash']);
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, email, password_hash)
             VALUES (:id, :email, :hash)
             ON CONFLICT (id) DO UPDATE SET email = :email, password_hash = :hash',
        );
        $stmt->execute([
            'id' => $user->id,
            'email' => $user->email,
            'hash' => $user->passwordHash,
        ]);
    }
}
