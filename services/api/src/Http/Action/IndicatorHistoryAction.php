<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Indicator\ListIndicatorHistory;
use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class IndicatorHistoryAction
{
    use JsonResponder;

    public function __construct(private readonly ListIndicatorHistory $history)
    {
    }

    /** @param array<string, string> $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $query = $request->getQueryParams();
        $months = filter_var($query['months'] ?? 24, FILTER_VALIDATE_INT);
        if ($months === false) {
            throw new \InvalidArgumentException('months must be an integer.');
        }

        return $this->json($response, $this->history->handle($args['key'] ?? '', $months));
    }
}
