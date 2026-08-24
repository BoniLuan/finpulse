<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\History\DeleteHistory;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DeleteHistoryAction
{
    use JsonResponder;

    public function __construct(private readonly DeleteHistory $history)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $deleted = $this->history->handle(
            (string) ($args['id'] ?? ''),
            (string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR),
        );

        if (!$deleted) {
            return $this->json($response, [
                'error' => ['code' => 'not_found', 'message' => 'history row not found'],
            ], 404);
        }

        return $response->withStatus(204);
    }
}
