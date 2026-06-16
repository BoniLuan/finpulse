<?php

declare(strict_types=1);

namespace FinPulse\Http;

use Psr\Http\Message\ResponseInterface;

/** Small helper to write JSON bodies onto a PSR-7 response. */
trait JsonResponder
{
    /** @param array<string, mixed>|list<mixed> $data */
    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
