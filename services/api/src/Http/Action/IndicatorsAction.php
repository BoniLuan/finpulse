<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Indicator\ListIndicators;
use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class IndicatorsAction
{
    use JsonResponder;

    public function __construct(private readonly ListIndicators $listIndicators)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['indicators' => $this->listIndicators->handle()]);
    }
}
