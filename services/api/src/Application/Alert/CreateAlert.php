<?php

declare(strict_types=1);

namespace FinPulse\Application\Alert;

use FinPulse\Domain\Alert\Alert;
use FinPulse\Domain\Alert\AlertMetric;
use FinPulse\Domain\Alert\AlertRepository;
use Ramsey\Uuid\Uuid;

final class CreateAlert
{
    public function __construct(private readonly AlertRepository $alerts)
    {
    }

    public function handle(
        string $userId,
        string $indicator,
        string $operator,
        float $threshold,
        string $channel = 'log',
    ): string {
        $ind = AlertMetric::fromName($indicator)
            ?? throw new \InvalidArgumentException('unknown indicator');

        $alert = new Alert(
            Uuid::uuid4()->toString(),
            $userId,
            $ind,
            $operator,
            $threshold,
            $channel,
        );
        $this->alerts->save($alert);

        return $alert->id;
    }
}
