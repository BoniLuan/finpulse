<?php

declare(strict_types=1);

namespace FinPulse\Tests\Unit;

use FinPulse\Application\Port\RateLimitCounter;
use FinPulse\Http\Middleware\AiRateLimitMiddleware;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use FinPulse\Http\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class RateLimitMiddlewareTest extends TestCase
{
    public function testAiLimitRequiresAnAuthenticatedUser(): void
    {
        $handler = new CountingHandler();
        $middleware = new AiRateLimitMiddleware(new InMemoryRateLimitCounter(), 5, 60, 50);

        $response = $middleware->process($this->request(), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(0, $handler->calls);
    }

    public function testAiMinuteLimitIsScopedByUser(): void
    {
        $handler = new CountingHandler();
        $middleware = new AiRateLimitMiddleware(new InMemoryRateLimitCounter(), 1, 60, 10);
        $userOne = $this->request()->withAttribute(JwtAuthMiddleware::USER_ATTR, "user-1");
        $userTwo = $this->request()->withAttribute(JwtAuthMiddleware::USER_ATTR, "user-2");

        self::assertSame(204, $middleware->process($userOne, $handler)->getStatusCode());
        $limited = $middleware->process($userOne, $handler);
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame("60", $limited->getHeaderLine("Retry-After"));
        self::assertSame(204, $middleware->process($userTwo, $handler)->getStatusCode());
    }

    public function testAiDailyLimitIsEnforced(): void
    {
        $handler = new CountingHandler();
        $middleware = new AiRateLimitMiddleware(new InMemoryRateLimitCounter(), 10, 60, 1);
        $request = $this->request()->withAttribute(JwtAuthMiddleware::USER_ATTR, "user-1");

        self::assertSame(204, $middleware->process($request, $handler)->getStatusCode());
        $limited = $middleware->process($request, $handler);
        self::assertSame(429, $limited->getStatusCode());
        self::assertSame("86400", $limited->getHeaderLine("Retry-After"));
    }

    public function testGlobalLimitIsScopedByRealClientIp(): void
    {
        $handler = new CountingHandler();
        $middleware = new RateLimitMiddleware(new InMemoryRateLimitCounter(), 1, 60);
        $clientOne = $this->request()->withHeader("X-Real-IP", "203.0.113.10");
        $clientTwo = $this->request()->withHeader("X-Real-IP", "203.0.113.11");

        self::assertSame(204, $middleware->process($clientOne, $handler)->getStatusCode());
        self::assertSame(429, $middleware->process($clientOne, $handler)->getStatusCode());
        self::assertSame(204, $middleware->process($clientTwo, $handler)->getStatusCode());
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest("POST", "/api/v1/ask");
    }
}

final class InMemoryRateLimitCounter implements RateLimitCounter
{
    /** @var array<string, int> */
    private array $counts = [];

    public function incrementWithWindow(string $key, int $window): int
    {
        return $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }
}

final class CountingHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;

        return new Response(204);
    }
}
