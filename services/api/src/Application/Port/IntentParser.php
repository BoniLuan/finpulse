<?php

declare(strict_types=1);

namespace FinPulse\Application\Port;

use FinPulse\Application\Ask\Intent;

/** Turns a natural-language question into a structured Intent (AI worker). */
interface IntentParser
{
    public function parse(string $question): Intent;
}
