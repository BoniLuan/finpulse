<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Alert\DeleteAlert;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DeleteAlertAction
{
    use JsonResponder;

    public function __construct(private readonly DeleteAlert $deleteAlert)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $userId = (string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR);
        $deleted = $this->deleteAlert->handle($args['id'] ?? '', $userId);

        if (!$deleted) {
            return $this->json($response, [
                'error' => ['code' => 'not_found', 'message' => 'alert not found'],
            ], 404);
        }

        return $response->withStatus(204);
    }
}
