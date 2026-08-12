<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\ReplenishmentPort;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

final class HeuristicReplenishmentAdapter implements ReplenishmentPort
{
    public function suggest(string $warehouseId): array
    {
        $onHandByProduct = Batch::query()
            ->select('product_id', DB::raw('COALESCE(SUM(quantity_on_hand), 0) as qty'))
            ->where('warehouse_id', $warehouseId)
            ->where('status', Batch::STATUS_ACTIVE)
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $suggestions = [];

        Product::query()
            ->whereNotNull('min_stock')
            ->orderBy('sku')
            ->each(function (Product $product) use ($onHandByProduct, $warehouseId, &$suggestions): void {
                $qty = (string) ($onHandByProduct[$product->id] ?? '0');
                $minStock = (string) $product->min_stock;

                if (bccomp($qty, $minStock, 3) > 0) {
                    return;
                }

                $suggested = bcsub($minStock, $qty, 3);
                if (bccomp($suggested, '0', 3) <= 0) {
                    $suggested = $minStock;
                }

                $suggestions[] = [
                    'product_id' => (string) $product->id,
                    'warehouse_id' => $warehouseId,
                    'suggested_qty' => $suggested,
                    'reason' => "Quantity on hand ({$qty}) is at or below min_stock ({$minStock}).",
                ];
            });

        return $suggestions;
    }
}
