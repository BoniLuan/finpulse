<?php

declare(strict_types=1);

namespace FinPulse\Application\Alert;

use FinPulse\Application\Port\IndicatorDataProvider;
use FinPulse\Application\Port\NotificationChannel;
use FinPulse\Domain\Alert\AlertRepository;

/**
 * Evaluates every stored alert against live data and dispatches a notification
 * for each triggered one. Invoked by `bin/console alerts:check` (cron-style).
 */
final class CheckAlerts
{
    /** @param array<string, NotificationChannel> $channels keyed by channel name */
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly IndicatorDataProvider $data,
        private readonly array $channels,
    ) {
    }

    /** @return int number of alerts triggered */
    public function handle(): int
    {
        $triggered = 0;
        foreach ($this->alerts->all() as $alert) {
            $value = $this->data->latest($alert->indicator);
            if (!$alert->isTriggeredBy($value)) {
                continue;
            }

            $channel = $this->channels[$alert->channel] ?? $this->channels['log'];
            $channel->send(
                $alert->userId,
                sprintf(
                    'Alerta: %s está em %.4f (condição: %s %.4f).',
                    $alert->indicator->label(),
                    $value,
                    $alert->operator,
                    $alert->threshold,
                ),
            );
            $triggered++;
        }

        return $triggered;
    }
}
