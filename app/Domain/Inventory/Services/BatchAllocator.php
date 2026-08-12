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

    /**
     * Allocate quantity across available batches using FEFO or FIFO.
     *
     * @return list<array{batch_id: string, product_id: string, warehouse_id: string, quantity: string, unit_cost: string}>
     */
    public function allocate(
        string $productId,
        string $warehouseId,
        string $quantity,
        ?string $strategy = null,
    ): array {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('Allocation quantity must be greater than zero.');
        }

        $product = Product::query()->findOrFail($productId);
        $strategy ??= $product->allocation_strategy ?: self::STRATEGY_FEFO;
        $strategy = strtolower($strategy);

        if (! in_array($strategy, [self::STRATEGY_FEFO, self::STRATEGY_FIFO], true)) {
            throw new InvalidArgumentException("Unsupported allocation strategy [{$strategy}].");
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $strategy): array {
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
                throw new RuntimeException(
                    "Insufficient stock for product [{$productId}] in warehouse [{$warehouseId}]. Remaining: {$remaining}."
                );
            }

            return $allocations;
        });
    }
}
