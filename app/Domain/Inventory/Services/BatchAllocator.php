<?php

namespace App\Domain\Inventory\Services;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class BatchAllocator
{
    public const STRATEGY_FEFO = 'fefo';

    public const STRATEGY_FIFO = 'fifo';

    public const SHELF_LOT_PREFIX = 'RAYON-';

    /**
     * Allocate quantity across available batches using FEFO or FIFO.
     *
     * When sales do not enforce recorded stock, leftover quantity is assigned
     * to an existing lot (or a RAYON-{sku} shelf lot) so the till can sell
     * against physical shelf stock.
     *
     * @return list<array{batch_id: string, product_id: string, warehouse_id: string, quantity: string, unit_cost: string}>
     */
    public function allocate(
        string $productId,
        string $warehouseId,
        string $quantity,
        ?string $strategy = null,
        ?bool $enforceStock = null,
    ): array {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('Allocation quantity must be greater than zero.');
        }

        $product = Product::query()->findOrFail($productId);
        $strategy ??= $product->allocation_strategy ?: self::STRATEGY_FEFO;
        $strategy = strtolower($strategy);
        $enforceStock ??= (bool) config('manolya.sales.enforce_stock', false);

        if (! in_array($strategy, [self::STRATEGY_FEFO, self::STRATEGY_FIFO], true)) {
            throw new InvalidArgumentException("Unsupported allocation strategy [{$strategy}].");
        }

        return DB::transaction(function () use ($product, $productId, $warehouseId, $quantity, $strategy, $enforceStock): array {
            $query = Batch::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('status', Batch::STATUS_ACTIVE)
                ->where('quantity_on_hand', '>', 0)
                ->where(function ($builder): void {
                    $builder->whereNull('expires_at')
                        ->orWhereDate('expires_at', '>=', Carbon::today());
                });

            if ($strategy === self::STRATEGY_FEFO) {
                $query->orderByRaw('expires_at IS NULL')
                    ->orderBy('expires_at')
                    ->orderBy('created_at');
            } else {
                $query->orderBy('created_at')
                    ->orderBy('id');
            }

            $remaining = $quantity;
            $allocations = [];

            /** @var Batch $batch */
            foreach ($query->lockForUpdate()->get() as $batch) {
                if (bccomp($remaining, '0', 3) <= 0) {
                    break;
                }

                $available = (string) $batch->quantity_on_hand;
                $take = bccomp($available, $remaining, 3) >= 0 ? $remaining : $available;

                if (bccomp($take, '0', 3) <= 0) {
                    continue;
                }

                $allocations[] = [
                    'batch_id' => (string) $batch->id,
                    'product_id' => (string) $batch->product_id,
                    'warehouse_id' => (string) $batch->warehouse_id,
                    'quantity' => $take,
                    'unit_cost' => (string) $batch->unit_cost,
                ];

                $remaining = bcsub($remaining, $take, 3);
            }

            if (bccomp($remaining, '0', 3) > 0) {
                if ($enforceStock) {
                    throw new RuntimeException(
                        "Insufficient stock for product [{$productId}] in warehouse [{$warehouseId}]. Remaining: {$remaining}."
                    );
                }

                $allocations = $this->assignOversellRemainder(
                    $allocations,
                    $product,
                    $productId,
                    $warehouseId,
                    $remaining,
                );
            }

            return $allocations;
        });
    }

    /**
     * @param  list<array{batch_id: string, product_id: string, warehouse_id: string, quantity: string, unit_cost: string}>  $allocations
     * @return list<array{batch_id: string, product_id: string, warehouse_id: string, quantity: string, unit_cost: string}>
     */
    private function assignOversellRemainder(
        array $allocations,
        Product $product,
        string $productId,
        string $warehouseId,
        string $remaining,
    ): array {
        if ($allocations !== []) {
            $last = array_key_last($allocations);
            $allocations[$last]['quantity'] = bcadd($allocations[$last]['quantity'], $remaining, 3);

            return $allocations;
        }

        $batch = $this->resolveShelfBatch($product, $productId, $warehouseId);

        $allocations[] = [
            'batch_id' => (string) $batch->id,
            'product_id' => (string) $batch->product_id,
            'warehouse_id' => (string) $batch->warehouse_id,
            'quantity' => $remaining,
            'unit_cost' => (string) $batch->unit_cost,
        ];

        return $allocations;
    }

    public static function shelfLotNumber(Product $product): string
    {
        return self::SHELF_LOT_PREFIX.$product->sku;
    }

    private function resolveShelfBatch(Product $product, string $productId, string $warehouseId): Batch
    {
        $shelfLot = self::shelfLotNumber($product);

        $existing = Batch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('lot_number', $shelfLot)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof Batch) {
            return $this->reactivateIfDepleted($existing);
        }

        $fallback = Batch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', [Batch::STATUS_ACTIVE, Batch::STATUS_DEPLETED])
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->first();

        if ($fallback instanceof Batch) {
            return $this->reactivateIfDepleted($fallback);
        }

        return Batch::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'lot_number' => $shelfLot,
            'quantity_on_hand' => '0',
            'unit_cost' => $product->purchase_price ?? '0',
            'currency_code' => $product->currency_code ?? 'CDF',
            'status' => Batch::STATUS_ACTIVE,
            'expires_at' => null,
        ]);
    }

    private function reactivateIfDepleted(Batch $batch): Batch
    {
        if ($batch->status === Batch::STATUS_DEPLETED) {
            $batch->status = Batch::STATUS_ACTIVE;
            $batch->save();
        }

        return $batch;
    }
}
