<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

use FinPulse\Application\Ask\Intent;

/** Turns a structured computation result into a plain-language answer (AI worker). */
interface AnswerWriter
{
    /** @param array<string, mixed> $result */
    public function write(Intent $intent, array $result): string;
}
