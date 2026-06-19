<?php

declare(strict_types=1);

namespace FinPulse\Application\Alert;

use FinPulse\Domain\Alert\Alert;
use FinPulse\Domain\Alert\AlertRepository;

final class ListAlerts
{
    public function __construct(private readonly AlertRepository $alerts)
    {
    }

    /** @return list<array{id: string, indicator: string, operator: string, threshold: float, channel: string}> */
    public function handle(string $userId): array
    {
        return array_map(
            static fn (Alert $a): array => [
                'id' => $a->id,
                'indicator' => $a->indicator->value,
                'operator' => $a->operator,
                'threshold' => $a->threshold,
                'channel' => $a->channel,
            ],
            $this->alerts->findByUser($userId),
        );
    }
}
