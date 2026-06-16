<?php

declare(strict_types=1);

namespace FinPulse\Http\Middleware;

use FinPulse\Application\Port\TokenIssuer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/** Validates `Authorization: Bearer <jwt>` and exposes the user id as a request attribute. */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public const USER_ATTR = 'user_id';

    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return $this->unauthorized('missing bearer token');
        }

        try {
            $userId = $this->tokens->verify($m[1]);
        } catch (\Throwable) {
            return $this->unauthorized('invalid or expired token');
        }

        return $handler->handle($request->withAttribute(self::USER_ATTR, $userId));
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => ['code' => 'unauthorized', 'message' => $message],
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }
}
