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
            'SELECT id, email, password_hash, display_name, phone FROM users WHERE email = :email',
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, display_name, phone FROM users WHERE id = :id',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, email, password_hash, display_name, phone)
             VALUES (:id, :email, :hash, :display_name, :phone)
             ON CONFLICT (id) DO UPDATE SET email = :email, password_hash = :hash,
                display_name = :display_name, phone = :phone',
        );
        $stmt->execute([
            'id' => $user->id,
            'email' => $user->email,
            'hash' => $user->passwordHash,
            'display_name' => $user->displayName,
            'phone' => $user->phone,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            $row['id'],
            $row['email'],
            $row['password_hash'],
            $row['display_name'] !== null ? (string) $row['display_name'] : null,
            $row['phone'] !== null ? (string) $row['phone'] : null,
        );
    }
}
