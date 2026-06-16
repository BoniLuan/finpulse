<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Auth;

use FinPulse\Application\Port\TokenIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService implements TokenIssuer
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    /** @return array{token: string, expires_in: int} */
    public function issue(string $userId): array
    {
        $now = time();
        $payload = [
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ];

        return [
            'token' => JWT::encode($payload, $this->secret, 'HS256'),
            'expires_in' => $this->ttl,
        ];
    }

    public function verify(string $token): string
    {
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));

        return (string) $decoded->sub;
    }
}
