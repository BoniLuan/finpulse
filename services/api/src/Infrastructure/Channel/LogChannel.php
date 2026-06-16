<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Channel;

use FinPulse\Application\Port\NotificationChannel;
use Psr\Log\LoggerInterface;

/** Default channel: writes notifications to the structured log. */
final class LogChannel implements NotificationChannel
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function name(): string
    {
        return 'log';
    }

    public function send(string $recipient, string $message): void
    {
        $this->logger->info('notification.dispatch', [
            'channel' => $this->name(),
            'recipient' => $recipient,
            'message' => $message,
        ]);
    }
}
