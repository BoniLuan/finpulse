<?php

declare(strict_types=1);

namespace FinPulse\Application\Ask;

final class AskResult
{
    /**
     * @param array<string, mixed>            $data
     * @param list<array<string, mixed>>      $sources
     */
    public function __construct(
        public readonly string $id,
        public readonly string $answer,
        public readonly array $data,
        public readonly array $sources,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'answer' => $this->answer,
            'data' => $this->data,
            'sources' => $this->sources,
        ];
    }
}
