<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Auth\LoginUser;
use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoginAction
{
    use JsonResponder;

    public function __construct(private readonly LoginUser $loginUser)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $token = $this->loginUser->handle(
            (string) ($body['email'] ?? ''),
            (string) ($body['password'] ?? ''),
        );

        return $this->json($response, $token);
    }
}
