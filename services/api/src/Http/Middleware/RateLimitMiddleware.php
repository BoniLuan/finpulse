<?php

declare(strict_types=1);

namespace FinPulse\Http\Middleware;

use FinPulse\Infrastructure\Cache\RedisCache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/** Fixed-window per-client rate limiting backed by Redis. */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RedisCache $cache,
        private readonly int $max,
        private readonly int $window,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getHeaderLine('X-Real-IP')
            ?: ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
        $key = 'ratelimit:' . $ip;

        $count = $this->cache->incrementWithWindow($key, $this->window);
        if ($count > $this->max) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'error' => ['code' => 'rate_limited', 'message' => 'too many requests'],
            ], JSON_THROW_ON_ERROR));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) $this->window)
                ->withStatus(429);
        }

        return $handler->handle($request);
    }
}
