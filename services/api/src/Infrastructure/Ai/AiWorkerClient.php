<?php

declare(strict_types=1);

namespace FinPulse\Infrastructure\Ai;

use FinPulse\Application\Ask\Intent;
use FinPulse\Application\Port\AnswerWriter;
use FinPulse\Application\Port\IntentParser;
use GuzzleHttp\ClientInterface;

/**
 * HTTP adapter to the Python ai-worker. Implements both AI ports.
 *
 *   POST {base}/infer/intent   { question }          → { type, params }
 *   POST {base}/infer/explain  { intent, result }    → { answer }
 */
final class AiWorkerClient implements IntentParser, AnswerWriter
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
    ) {
    }

    public function parse(string $question): Intent
    {
        $body = $this->post('/infer/intent', ['question' => $question]);

        return new Intent(
            (string) ($body['type'] ?? Intent::INDICATOR_VALUE),
            is_array($body['params'] ?? null) ? $body['params'] : [],
        );
    }

    /** @param array<string, mixed> $result */
    public function write(Intent $intent, array $result): string
    {
        $body = $this->post('/infer/explain', [
            'intent' => ['type' => $intent->type, 'params' => $intent->params],
            'result' => $result,
        ]);

        return (string) ($body['answer'] ?? '');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->http->request('POST', rtrim($this->baseUrl, '/') . $path, [
            'json' => $payload,
            'timeout' => 30,
        ]);

        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
