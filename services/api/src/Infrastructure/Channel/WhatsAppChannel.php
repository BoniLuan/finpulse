<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Channel;

use FinPulse\Application\Port\NotificationChannel;

/**
 * WhatsApp channel — STUB.
 *
 * Implementing this is the documented next step: wire the Meta WhatsApp Cloud
 * API (or Z-API) here. The rest of the system already dispatches through the
 * NotificationChannel port, so no other code needs to change — register this
 * channel in config/container.php under the key 'whatsapp'.
 *
 * @see docs/architecture.md
 */
final class WhatsAppChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(string $recipient, string $message): void
    {
        throw new \RuntimeException(
            'WhatsAppChannel is not implemented yet. '
            . 'Wire the WhatsApp Cloud API here and register it in the container.',
        );
    }
}
