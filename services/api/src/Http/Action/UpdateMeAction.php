<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Auth\UpdateProfile;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UpdateMeAction
{
    use JsonResponder;

    public function __construct(private readonly UpdateProfile $updateProfile)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $user = $this->updateProfile->handle(
            (string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR),
            (string) ($body['display_name'] ?? ''),
            (string) ($body['phone'] ?? ''),
        );

        return $this->json($response, [
            'id' => $user->id,
            'email' => $user->email,
            'display_name' => $user->displayName,
            'phone' => $user->phone,
        ]);
    }
}
