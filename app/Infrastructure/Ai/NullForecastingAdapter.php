<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\ForecastingPort;

final class NullForecastingAdapter implements ForecastingPort
{
    public function predictDemand(string $productId, int $horizonDays): array
    {
        return [
            'product_id' => $productId,
            'horizon_days' => $horizonDays,
            'predicted_qty' => '0',
            'confidence' => null,
        ];
    }
}
