<?php

declare(strict_types=1);

namespace FinPulse\Application\Ask;

/**
 * Structured interpretation of a natural-language question, produced by the
 * AI worker and consumed by the AskQuestion use case.
 */
final class Intent
{
    public const INDICATOR_VALUE = 'indicator_value';
    public const INVESTMENT_RETURN = 'investment_return';
    public const INFLATION_CORRECTION = 'inflation_correction';

    /** @param array<string, mixed> $params */
    public function __construct(
        public readonly string $type,
        public readonly array $params = [],
    ) {
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
