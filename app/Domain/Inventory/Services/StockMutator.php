<?php

namespace App\Domain\Inventory\Services;

use App\Models\Batch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class StockMutator
{
    private const OUTBOUND_TYPES = [
        StockMovement::TYPE_OUT_SALE,
        StockMovement::TYPE_OUT_RETURN_SUPPLIER,
        StockMovement::TYPE_OUT_ADJUSTMENT,
        StockMovement::TYPE_OUT_EXPIRED,
    ];

    private const INBOUND_TYPES = [
        StockMovement::TYPE_IN_PURCHASE,
        StockMovement::TYPE_IN_RETURN,
        StockMovement::TYPE_IN_ADJUSTMENT,
    ];

    /**
     * Create an immutable stock movement and update batch quantity atomically.
     *
     * @param  array{
     *     batch_id: string,
     *     type: string,
     *     quantity: string|float|int,
     *     unit_cost?: string|float|int|null,
     *     reference_type?: string|null,
     *     reference_id?: string|null,
     *     user_id?: string|null,
     *     notes?: string|null,
     *     occurred_at?: \DateTimeInterface|string|null,
     *     tenant_id?: string|null
     * }  $payload
     */
    public function mutate(array $payload): StockMovement
    {
        $type = (string) ($payload['type'] ?? '');
        $quantity = $this->normalizeQuantity($payload['quantity'] ?? null);

        if ($quantity === '0.000') {
            throw new InvalidArgumentException('Stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($payload, $type, $quantity): StockMovement {
            /** @var Batch $batch */
            $batch = Batch::query()
                ->whereKey($payload['batch_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $signedQty = $this->signedQuantity($type, $quantity);
            $newQty = bcadd((string) $batch->quantity_on_hand, $signedQty, 3);

            if (bccomp($newQty, '0', 3) < 0) {
                throw new RuntimeException(
                    "Batch [{$batch->id}] would go negative (on hand {$batch->quantity_on_hand}, delta {$signedQty})."
                );
            }

            $batch->quantity_on_hand = $newQty;

            if (bccomp($newQty, '0', 3) === 0 && $batch->status === Batch::STATUS_ACTIVE) {
                $batch->status = Batch::STATUS_DEPLETED;
            }

            if (bccomp($newQty, '0', 3) > 0 && $batch->status === Batch::STATUS_DEPLETED) {
                $batch->status = Batch::STATUS_ACTIVE;
            }

            $batch->save();

            return StockMovement::query()->create([
                'tenant_id' => $payload['tenant_id'] ?? $batch->tenant_id,
                'batch_id' => $batch->id,
                'product_id' => $batch->product_id,
                'warehouse_id' => $batch->warehouse_id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $payload['unit_cost'] ?? $batch->unit_cost,
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
                'user_id' => $payload['user_id'] ?? auth()->id(),
                'notes' => $payload['notes'] ?? null,
                'occurred_at' => $payload['occurred_at'] ?? now(),
                'created_at' => now(),
            ]);
        });
    }

    private function normalizeQuantity(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            throw new InvalidArgumentException('Stock movement quantity is required.');
        }

        $normalized = is_string($quantity)
            ? $quantity
            : number_format((float) $quantity, 3, '.', '');

        if (bccomp($normalized, '0', 3) < 0) {
            throw new InvalidArgumentException('Pass absolute quantity; direction is derived from movement type.');
        }

        return $normalized;
    }

    private function signedQuantity(string $type, string $quantity): string
    {
        if (in_array($type, self::OUTBOUND_TYPES, true)) {
            return bcmul($quantity, '-1', 3);
        }

        if (in_array($type, self::INBOUND_TYPES, true)) {
            return $quantity;
        }

        if ($type === StockMovement::TYPE_TRANSFER) {
            throw new InvalidArgumentException(
                'TRANSFER movements must be expressed as paired IN/OUT mutations.'
            );
        }

        throw new InvalidArgumentException("Unknown stock movement type [{$type}].");
    }
}
