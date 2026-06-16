<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Auth\RegisterUser;
use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegisterAction
{
    use JsonResponder;

    public function __construct(private readonly RegisterUser $registerUser)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $id = $this->registerUser->handle(
            (string) ($body['email'] ?? ''),
            (string) ($body['password'] ?? ''),
        );

        return $this->json($response, ['id' => $id], 201);
    }
}
