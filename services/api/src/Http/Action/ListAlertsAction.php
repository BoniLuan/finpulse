<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Alert\ListAlerts;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ListAlertsAction
{
    use JsonResponder;

    public function __construct(private readonly ListAlerts $listAlerts)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR);

        return $this->json($response, ['alerts' => $this->listAlerts->handle($userId)]);
    }
}
