<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Alert\DeleteAlert;
use FinPulse\Application\Alert\ListAlerts;
use FinPulse\Domain\Alert\Alert;
use FinPulse\Domain\Alert\AlertRepository;
use FinPulse\Domain\Finance\Indicator;
use PHPUnit\Framework\TestCase;

final class UserAlertsTest extends TestCase
{
    public function testListReturnsOnlyTheUsersAlerts(): void
    {
        $repo = new InMemoryAlertRepository();
        $repo->save(new Alert('a1', 'user-1', Indicator::USD, '>', 5.0, 'log'));
        $repo->save(new Alert('a2', 'user-2', Indicator::SELIC, '<', 10.0, 'log'));

        $list = (new ListAlerts($repo))->handle('user-1');

        self::assertCount(1, $list);
        self::assertSame('a1', $list[0]['id']);
        self::assertSame('usd', $list[0]['indicator']);
    }

    public function testDeleteOnlyRemovesOwnedAlert(): void
    {
        $repo = new InMemoryAlertRepository();
        $repo->save(new Alert('a1', 'user-1', Indicator::USD, '>', 5.0, 'log'));
        $delete = new DeleteAlert($repo);

        self::assertFalse($delete->handle('a1', 'user-2')); // not the owner
        self::assertTrue($delete->handle('a1', 'user-1'));   // owner
        self::assertSame([], (new ListAlerts($repo))->handle('user-1'));
    }
}

final class InMemoryAlertRepository implements AlertRepository
{
    /** @var array<string, Alert> */
    private array $alerts = [];

    public function save(Alert $alert): void
    {
        $this->alerts[$alert->id] = $alert;
    }

    /** @return list<Alert> */
    public function all(): array
    {
        return array_values($this->alerts);
    }

    /** @return list<Alert> */
    public function findByUser(string $userId): array
    {
        return array_values(array_filter(
            $this->alerts,
            static fn (Alert $a): bool => $a->userId === $userId,
        ));
    }

    public function deleteForUser(string $id, string $userId): bool
    {
        if (isset($this->alerts[$id]) && $this->alerts[$id]->userId === $userId) {
            unset($this->alerts[$id]);

            return true;
        }

        return false;
    }
}
