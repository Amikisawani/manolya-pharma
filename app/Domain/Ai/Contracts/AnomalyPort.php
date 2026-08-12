<?php

namespace App\Domain\Ai\Contracts;

interface AnomalyPort
{
    /**
     * @param  array<int, array<string, mixed>>  $salesWindow
     * @return list<array{type: string, severity: string, message: string, context: array<string, mixed>}>
     */
    public function detect(array $salesWindow): array;
}
