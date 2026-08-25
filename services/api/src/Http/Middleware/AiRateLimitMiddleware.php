<?php

declare(strict_types=1);

namespace FinPulse\Http\Middleware;

use FinPulse\Application\Port\RateLimitCounter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/** Per-user minute and daily quotas for endpoints that consume paid AI capacity. */
final class AiRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimitCounter $counter,
        private readonly int $max,
        private readonly int $window,
        private readonly int $dailyMax,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getAttribute(JwtAuthMiddleware::USER_ATTR);
        if (!is_string($userId) || $userId === '') {
            return $this->error('unauthorized', 'authentication is required', 401);
        }

        $identity = hash('sha256', $userId);
        $minuteCount = $this->counter->incrementWithWindow(
            'ratelimit:ai:minute:' . $identity,
            $this->window,
        );
        if ($minuteCount > $this->max) {
            return $this->rateLimited($this->window, 'AI request limit exceeded; try again shortly');
        }

        $dailyCount = $this->counter->incrementWithWindow(
            'ratelimit:ai:daily:' . $identity,
            86400,
        );
        if ($dailyCount > $this->dailyMax) {
            return $this->rateLimited(86400, 'daily AI request limit exceeded');
        }

        return $handler->handle($request);
    }

    private function rateLimited(int $retryAfter, string $message): ResponseInterface
    {
        return $this->error('rate_limited', $message, 429)
            ->withHeader('Retry-After', (string) $retryAfter);
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
