<?php

declare(strict_types=1);

namespace FinPulse\Http\Middleware;

use FinPulse\Application\Port\TokenIssuer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Adds a user id when a valid bearer token exists; guests continue anonymously. */
final class OptionalJwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $handler->handle($request);
        }

        try {
            $userId = $this->tokens->verify($matches[1]);
        } catch (\Throwable) {
            return $handler->handle($request);
        }

        return $handler->handle($request->withAttribute(JwtAuthMiddleware::USER_ATTR, $userId));
    }
}
