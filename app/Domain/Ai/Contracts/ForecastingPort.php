<?php

namespace App\Domain\Ai\Contracts;

interface ForecastingPort
{
    /**
     * @return array{product_id: string, horizon_days: int, predicted_qty: string, confidence: float|null}
     */
    public function predictDemand(string $productId, int $horizonDays): array;
}
