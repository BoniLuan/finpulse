<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Auth\RegisterUser;
use FinPulse\Application\Auth\UpdateProfile;
use FinPulse\Domain\User\User;
use FinPulse\Domain\User\UserRepository;
use PHPUnit\Framework\TestCase;

final class UserProfileTest extends TestCase
{
    public function testRegistrationStoresNameAndNormalizedPhone(): void
    {
        $users = new ProfileUserRepository();
        $id = (new RegisterUser($users))->handle(
            'demo@example.com',
            'password123',
            'Demo User',
            '+55 (18) 99999-9999',
        );

        $user = $users->findById($id);
        self::assertSame('Demo User', $user?->displayName);
        self::assertSame('5518999999999', $user?->phone);
    }

    public function testExistingUserCanUpdateProfileAndRemovePhone(): void
    {
        $users = new ProfileUserRepository();
        $users->save(new User('u1', 'demo@example.com', 'hash', null, '5518999999999'));

        $updated = (new UpdateProfile($users))->handle('u1', 'Luan', '');

        self::assertSame('Luan', $updated->displayName);
        self::assertNull($updated->phone);
    }
}

final class ProfileUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[$user->id] = $user;
    }
}
