<?php

declare(strict_types=1);

namespace FinPulse\Http\Action;

use FinPulse\Application\Indicator\CompareSelicIpca;
use FinPulse\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SelicIpcaComparisonAction
{
    use JsonResponder;

    public function __construct(private readonly CompareSelicIpca $comparison)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $months = filter_var($request->getQueryParams()['months'] ?? 24, FILTER_VALIDATE_INT);
        if ($months === false) {
            throw new \InvalidArgumentException('months must be an integer.');
        }

        return $this->json($response, $this->comparison->compare($months));
    }
}
