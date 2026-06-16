<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Alert\CreateAlert;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CreateAlertAction
{
    use JsonResponder;

    public function __construct(private readonly CreateAlert $createAlert)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR);
        $body = (array) $request->getParsedBody();

        $id = $this->createAlert->handle(
            $userId,
            (string) ($body['indicator'] ?? ''),
            (string) ($body['operator'] ?? '>'),
            (float) ($body['threshold'] ?? 0),
            (string) ($body['channel'] ?? 'log'),
        );

        return $this->json($response, ['id' => $id], 201);
    }
}
