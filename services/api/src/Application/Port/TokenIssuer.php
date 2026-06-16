<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

/** Issues and verifies auth tokens (implemented by JwtService). */
interface TokenIssuer
{
    /** @return array{token: string, expires_in: int} */
    public function issue(string $userId): array;

    /** @return string the subject (user id) if valid */
    public function verify(string $token): string;
}
