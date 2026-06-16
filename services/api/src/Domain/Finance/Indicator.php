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
    case SELIC = 'selic';      // meta Selic anual
    case CDI = 'cdi';          // CDI diário
    case IPCA = 'ipca';        // IPCA mensal (%)
    case USD = 'usd';          // Dólar compra (PTAX)
    case POUPANCA = 'poupanca'; // Rendimento mensal da poupança

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
            self::SELIC => 'Meta Selic (a.a.)',
            self::CDI => 'CDI (diário)',
            self::IPCA => 'IPCA (mensal)',
            self::USD => 'Dólar PTAX (compra)',
            self::POUPANCA => 'Poupança (mensal)',
        };
    }

    public static function fromName(string $name): ?self
    {
        return self::tryFrom(strtolower(trim($name)));
    }
}
