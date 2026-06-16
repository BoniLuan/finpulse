<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

/**
 * Outbound notification channel. New channels (WhatsApp, email, ...) are added
 * by implementing this interface — never by branching on a channel type.
 */
interface NotificationChannel
{
    public function name(): string;

    public function send(string $recipient, string $message): void;
}
