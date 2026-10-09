<?php

namespace App\Domain\Inventory\Services;

use App\Models\Batch;

/**
 * Valeur du stock encore présent et bénéfice si ce stock est vendu au prix catalogue.
 */
final class StockValuation
{
    /**
     * @return array{
     *     cost: string,
     *     sale_value: string,
     *     expected_profit: string,
     *     units: string,
     *     products: int
     * }
     */
    public function present(): array
    {
        $row = Batch::query()
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->where('batches.quantity_on_hand', '>', 0)
            ->whereNull('products.deleted_at')
            ->selectRaw('COALESCE(SUM(batches.quantity_on_hand * batches.unit_cost), 0) as cost')
            ->selectRaw('COALESCE(SUM(batches.quantity_on_hand * products.sale_price), 0) as sale_value')
            ->selectRaw('COALESCE(SUM(batches.quantity_on_hand), 0) as units')
            ->selectRaw('COUNT(DISTINCT batches.product_id) as products')
            ->first();

        $cost = $this->money($row->cost ?? 0);
        $saleValue = $this->money($row->sale_value ?? 0);

        return [
            'cost' => $cost,
            'sale_value' => $saleValue,
            'expected_profit' => bcsub($saleValue, $cost, 2),
            'units' => $this->money($row->units ?? 0, 3),
            'products' => (int) ($row->products ?? 0),
        ];
    }

    private function money(mixed $value, int $scale = 2): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
