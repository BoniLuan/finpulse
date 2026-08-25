<?php

declare(strict_types=1);

namespace FinPulse\Tests\Integration;

use FinPulse\Application\Alert\CheckAlerts;
use FinPulse\Application\Port\AlertThrottle;
use FinPulse\Application\Port\CryptoPriceProvider;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\NotificationChannel;
use FinPulse\Domain\Alert\Alert;
use FinPulse\Domain\Alert\AlertMetric;
use FinPulse\Domain\Finance\Indicator;
use FinPulse\Domain\Finance\IndicatorSeries;
use FinPulse\Domain\User\User;
use FinPulse\Domain\User\UserRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CheckAlertsTest extends TestCase
{
    public function testTriggeredAlertFiresOnceThenCoolsDown(): void
    {
        $spy = new SpyChannel('log');
        $check = $this->build('log', $spy);

        self::assertSame(1, $check->handle());      // first run fires
        self::assertSame(0, $check->handle());      // cooldown blocks the re-fire
        self::assertCount(1, $spy->sent);
    }

    public function testEmailChannelResolvesUserEmailAsRecipient(): void
    {
        $spy = new SpyChannel('email');
        $check = $this->build('email', $spy, new User('u1', 'demo@finpulse.dev', 'x'));

        $check->handle();

        self::assertSame('demo@finpulse.dev', $spy->sent[0]['to']);
    }

    public function testWhatsAppChannelUsesConfiguredRecipient(): void
    {
        $spy = new SpyChannel('whatsapp');
        $check = $this->build(
            'whatsapp',
            $spy,
            new User('u1', 'demo@finpulse.dev', 'x', 'Demo user', '5518999999999'),
        );

        self::assertSame(1, $check->handle());
        self::assertSame('5518999999999', $spy->sent[0]['to']);
    }

    public function testEmailWithoutAUserIsSkipped(): void
    {
        $spy = new SpyChannel('email');
        $check = $this->build('email', $spy);

        self::assertSame(0, $check->handle());
        self::assertSame([], $spy->sent);
    }

    public function testCryptoAlertUsesTheCryptoPriceProvider(): void
    {
        $spy = new SpyChannel('log');
        $check = $this->build('log', $spy, null, AlertMetric::BTC, 400000.0);

        self::assertSame(1, $check->handle());
        self::assertStringContainsString('Bitcoin price in BRL', $spy->sent[0]['msg']);
    }

    private function build(
        string $channel,
        SpyChannel $spy,
        ?User $user = null,
        AlertMetric $metric = AlertMetric::USD,
        float $threshold = 5.0,
    ): CheckAlerts {
        $repo = new OneAlertRepository(new Alert('a1', 'u1', $metric, '>', $threshold, $channel));

        return new CheckAlerts(
            $repo,
            new TriggerProvider(6.0), // 6.0 > 5.0 → triggers
            new AlertCryptoProvider(),
            new StubUserRepo($user),
            new MemoryThrottle(),
            new NullLogger(),
            ['log' => $spy, 'email' => $spy, 'whatsapp' => $spy],
        );
    }
}

final class AlertCryptoProvider implements CryptoPriceProvider
{
    public function prices(): array
    {
        return ['btc' => 500000.0, 'eth' => 20000.0];
    }
}

final class SpyChannel implements NotificationChannel
{
    /** @var list<array{to: string, msg: string}> */
    public array $sent = [];

    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function send(string $recipient, string $message): void
    {
        $this->sent[] = ['to' => $recipient, 'msg' => $message];
    }
}

final class MemoryThrottle implements AlertThrottle
{
    /** @var array<string, true> */
    private array $sent = [];

    public function shouldSend(string $alertId): bool
    {
        return !isset($this->sent[$alertId]);
    }

    public function markSent(string $alertId): void
    {
        $this->sent[$alertId] = true;
    }
}

final class TriggerProvider implements IndicatorDataProvider
{
    public function __construct(private readonly float $value)
    {
    }

    public function latest(Indicator $indicator): float
    {
        return $this->value;
    }

    public function series(Indicator $indicator, int $lastN): IndicatorSeries
    {
        return new IndicatorSeries($indicator, []);
    }
}

final class StubUserRepo implements UserRepository
{
    public function __construct(private readonly ?User $user)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->user;
    }

    public function findById(string $id): ?User
    {
        return $this->user;
    }

    public function save(User $user): void
    {
    }
}

final class OneAlertRepository implements \FinPulse\Domain\Alert\AlertRepository
{
    public function __construct(private readonly Alert $alert)
    {
    }

    public function save(Alert $alert): void
    {
    }

    /** @return list<Alert> */
    public function all(): array
    {
        return [$this->alert];
    }

    /** @return list<Alert> */
    public function findByUser(string $userId): array
    {
        return [$this->alert];
    }

    public function deleteForUser(string $id, string $userId): bool
    {
        return true;
    }
}
