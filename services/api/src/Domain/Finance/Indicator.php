<?php

declare(strict_types=1);

namespace FinPulse\Domain\Finance;

/**
 * Supported economic indicators and their BACEN SGS series codes.
 *
 * Series codes reference the BACEN "Sistema Gerenciador de Séries Temporais":
 * https://api.bcb.gov.br/dados/serie/bcdata.sgs.{code}/dados
 */
enum Indicator: string
{
    case SELIC = 'selic';      // annual Selic target rate
    case CDI = 'cdi';          // daily CDI rate
    case IPCA = 'ipca';        // monthly IPCA inflation (%)
    case USD = 'usd';          // USD buy rate (PTAX)
    case POUPANCA = 'poupanca'; // monthly savings yield

    public function seriesCode(): int
    {
        return match ($this) {
            self::SELIC => 432,
            self::CDI => 12,
            self::IPCA => 433,
            self::USD => 1,
            self::POUPANCA => 196,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SELIC => 'Selic target rate (annual)',
            self::CDI => 'CDI rate (daily)',
            self::IPCA => 'IPCA inflation (monthly)',
            self::USD => 'USD/BRL PTAX (buy)',
            self::POUPANCA => 'Savings yield (monthly)',
        };
    }

    public static function fromName(string $name): ?self
    {
        return self::tryFrom(strtolower(trim($name)));
    }
}
