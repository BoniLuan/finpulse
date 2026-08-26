<?php

declare(strict_types=1);

namespace FinPulse\Tests\Unit;

use FinPulse\Application\Ask\Intent;
use FinPulse\Infrastructure\Ai\AiWorkerClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AiWorkerClientTest extends TestCase
{
    public function testEmptyIntentParamsAreSentAsAJsonObject(): void
    {
        $history = [];
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"answer":"fallback answer"}'),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $client = new AiWorkerClient(new Client(['handler' => $stack]), 'http://ai-worker:8000');

        $answer = $client->write(new Intent(Intent::INDICATOR_VALUE), [
            'type' => Intent::INDICATOR_VALUE,
            'indicator' => 'selic',
            'value' => 10.5,
        ]);

        self::assertSame('fallback answer', $answer);
        self::assertCount(1, $history);
        self::assertSame(
            '{"intent":{"type":"indicator_value","params":{}},"result":{"type":"indicator_value","indicator":"selic","value":10.5}}',
            (string) $history[0]['request']->getBody(),
        );
    }
}
