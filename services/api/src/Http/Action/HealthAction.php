<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthAction
{
    use JsonResponder;

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['status' => 'ok', 'service' => 'api']);
    }
}
