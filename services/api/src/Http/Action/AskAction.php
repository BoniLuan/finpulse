<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Ask\AskQuestion;
use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AskAction
{
    use JsonResponder;

    public function __construct(private readonly AskQuestion $askQuestion)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $question = trim((string) ($body['question'] ?? ''));

        if ($question === '') {
            return $this->json($response, [
                'error' => ['code' => 'validation', 'message' => 'question is required'],
            ], 422);
        }

        $result = $this->askQuestion->handle($question);

        return $this->json($response, $result->toArray());
    }
}
