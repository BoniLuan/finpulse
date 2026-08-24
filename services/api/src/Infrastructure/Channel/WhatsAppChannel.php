<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Channel;

use FinPulse\Application\Port\NotificationChannel;
use GuzzleHttp\ClientInterface;

/**
 * WhatsApp channel via the Meta Cloud API (Graph API).
 *
 *   POST https://graph.facebook.com/{version}/{phoneNumberId}/messages
 *
 * Free in development: a test sender number plus verified test recipients. Free
 * text delivery requires an open 24h session (the recipient messages first);
 * see docs/architecture.md. Configure WHATSAPP_* env vars to enable.
 */
final class WhatsAppChannel implements NotificationChannel
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $token,
        private readonly string $phoneNumberId,
        private readonly string $apiVersion,
    ) {
    }

    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(string $recipient, string $message): void
    {
        if ($this->token === '' || $this->phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp channel is not configured (WHATSAPP_* env).');
        }
        if ($recipient === '') {
            throw new \RuntimeException('WhatsApp channel: empty recipient');
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $this->phoneNumberId,
        );

        $this->http->request('POST', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => ['body' => $message],
            ],
            'timeout' => 15,
        ]);
    }
}
