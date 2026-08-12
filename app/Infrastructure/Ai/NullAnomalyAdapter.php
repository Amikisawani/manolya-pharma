<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AnomalyPort;

/**
 * V1 stub — no ML. Returns empty set. Replace in V2.
 */
final class NullAnomalyAdapter implements AnomalyPort
{
    public function detect(array $salesWindow): array
    {
        return [];
    }
}
