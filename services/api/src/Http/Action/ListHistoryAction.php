<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\History\ListHistory;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ListHistoryAction
{
    use JsonResponder;

    public function __construct(private readonly ListHistory $history)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, [
            'history' => $this->history->handle((string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR)),
        ]);
    }
}
