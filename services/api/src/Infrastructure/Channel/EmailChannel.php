<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Channel;

use FinPulse\Application\Port\NotificationChannel;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/** Sends alert notifications by email over SMTP (symfony/mailer). */
final class EmailChannel implements NotificationChannel
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $from,
    ) {
    }

    public function name(): string
    {
        return 'email';
    }

    public function send(string $recipient, string $message): void
    {
        if ($recipient === '') {
            throw new \RuntimeException('email channel: empty recipient');
        }

        $email = (new Email())
            ->from($this->from)
            ->to($recipient)
            ->subject('FinPulse alert')
            ->text($message);

        $this->mailer->send($email);
    }
}
