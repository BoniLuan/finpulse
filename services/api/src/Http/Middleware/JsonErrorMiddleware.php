<?php

declare(strict_types=1);

namespace FinPulse\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Response;

/**
 * Converts uncaught exceptions into structured JSON errors.
 * Domain validation errors → 4xx; everything else → 500 (details hidden unless debug).
 */
final class JsonErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\InvalidArgumentException $e) {
            return $this->error('validation', $e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->error('bad_request', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            $this->logger->error('unhandled.exception', [
                'message' => $e->getMessage(),
                'class' => $e::class,
            ]);

            return $this->error(
                'internal_error',
                $this->debug ? $e->getMessage() : 'internal server error',
                500,
            );
        }
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
