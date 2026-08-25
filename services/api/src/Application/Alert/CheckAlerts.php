<?php

declare(strict_types=1);

namespace FinPulse\Application\Alert;

use FinPulse\Application\Port\AlertThrottle;
use FinPulse\Application\Port\CryptoPriceProvider;
use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\NotificationChannel;
use FinPulse\Domain\Alert\Alert;
use FinPulse\Domain\Alert\AlertRepository;
use FinPulse\Domain\User\UserRepository;
use Psr\Log\LoggerInterface;

/**
 * Evaluates every stored alert against live data and dispatches a notification
 * for each triggered one (subject to a per-alert cooldown). Invoked by
 * `bin/console alerts:check`, which the scheduler runs on an interval.
 */
final class CheckAlerts
{
    /** @param array<string, NotificationChannel> $channels keyed by channel name */
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly IndicatorDataProvider $data,
        private readonly CryptoPriceProvider $crypto,
        private readonly UserRepository $users,
        private readonly AlertThrottle $throttle,
        private readonly LoggerInterface $logger,
        private readonly array $channels,
    ) {
    }

    /** @return int number of notifications dispatched */
    public function handle(): int
    {
        $sent = 0;
        foreach ($this->alerts->all() as $alert) {
            $value = $this->valueFor($alert);
            if (!$alert->isTriggeredBy($value) || !$this->throttle->shouldSend($alert->id)) {
                continue;
            }

            $recipient = $this->recipientFor($alert);
            if ($recipient === '') {
                continue;
            }

            $channel = $this->channels[$alert->channel] ?? $this->channels['log'];
            try {
                $channel->send($recipient, $this->message($alert, $value));
                $this->throttle->markSent($alert->id);
                $sent++;
            } catch (\Throwable $e) {
                // One bad channel/recipient must not abort the whole batch.
                $this->logger->error('alert.dispatch_failed', [
                    'alert' => $alert->id,
                    'channel' => $alert->channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private function recipientFor(Alert $alert): string
    {
        $user = $this->users->findById($alert->userId);

        return match ($alert->channel) {
            'email' => $user?->email ?? '',
            'whatsapp' => $user?->phone ?? '',
            default => $alert->userId,
        };
    }

    private function valueFor(Alert $alert): float
    {
        $economicIndicator = $alert->indicator->economicIndicator();
        if ($economicIndicator !== null) {
            return $this->data->latest($economicIndicator);
        }

        $prices = $this->crypto->prices();

        return $prices[$alert->indicator->value]
            ?? throw new \RuntimeException('Crypto price is unavailable.');
    }

    private function message(Alert $alert, float $value): string
    {
        return sprintf(
            'Alert: %s is at %.4f (condition: %s %.4f).',
            $alert->indicator->label(),
            $value,
            $alert->operator,
            $alert->threshold,
        );
    }
}
