<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Domain\User\UserRepository;
use FinPulse\Http\JsonResponder;
use FinPulse\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MeAction
{
    use JsonResponder;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute(JwtAuthMiddleware::USER_ATTR);
        $user = $this->users->findById($userId);

        if ($user === null) {
            return $this->json($response, [
                'error' => ['code' => 'not_found', 'message' => 'user not found'],
            ], 404);
        }

        return $this->json($response, ['id' => $user->id, 'email' => $user->email]);
    }
}
