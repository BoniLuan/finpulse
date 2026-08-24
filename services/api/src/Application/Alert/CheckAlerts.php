<?php

declare(strict_types=1);

namespace FinPulse\Application\Alert;

use FinPulse\Application\Port\AlertThrottle;
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
        private readonly UserRepository $users,
        private readonly AlertThrottle $throttle,
        private readonly LoggerInterface $logger,
        private readonly array $channels,
        private readonly string $whatsAppRecipient,
    ) {
    }

    /** @return int number of notifications dispatched */
    public function handle(): int
    {
        $sent = 0;
        foreach ($this->alerts->all() as $alert) {
            $value = $this->data->latest($alert->indicator);
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
        return match ($alert->channel) {
            'email' => $this->users->findById($alert->userId)?->email ?? '',
            'whatsapp' => $this->whatsAppRecipient,
            default => $alert->userId,
        };
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
