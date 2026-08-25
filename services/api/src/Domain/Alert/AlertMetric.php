<?php

declare(strict_types=1);

namespace FinPulse\Domain\Alert;

use FinPulse\Domain\Finance\Indicator;

enum AlertMetric: string
{
    case SELIC = 'selic';
    case CDI = 'cdi';
    case IPCA = 'ipca';
    case USD = 'usd';
    case POUPANCA = 'poupanca';
    case BTC = 'btc';
    case ETH = 'eth';

    public static function fromName(string $name): ?self
    {
        return self::tryFrom(strtolower(trim($name)));
    }

    public function economicIndicator(): ?Indicator
    {
        return Indicator::tryFrom($this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::BTC => 'Bitcoin price in BRL',
            self::ETH => 'Ethereum price in BRL',
            default => $this->economicIndicator()?->label() ?? $this->value,
        };
    }
}
