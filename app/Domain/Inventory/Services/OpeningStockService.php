<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Exceptions\OpeningStockException;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\FlexibleDate;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OpeningStockService
{
    public function __construct(
        private readonly StockMutator $stockMutator,
    ) {}

    /**
     * @param  array{
     *     warehouse_id?: string|null,
     *     lot_number?: string|null,
     *     quantity: string|int|float,
     *     expires_at?: string|null,
     *     manufactured_at?: string|null,
     *     unit_cost?: string|int|float|null,
     *     user_id?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function receiveForProduct(Product $product, array $data): Batch
    {
        $quantity = $this->normalizeQuantity($data['quantity'] ?? 0);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException('La quantité de stock initial doit être supérieure à 0.');
        }

        $warehouse = $this->resolveWarehouse(
            $product,
            isset($data['warehouse_id']) && is_string($data['warehouse_id']) ? $data['warehouse_id'] : null,
        );

        $lotNumber = trim((string) ($data['lot_number'] ?? ''));
        if ($lotNumber === '') {
            $lotNumber = 'INI-'.$product->sku.'-'.now()->format('Ymd');
        }

        $unitCost = (string) ($data['unit_cost'] ?? $product->purchase_price ?? '0');
        $expiresAt = $this->nullableDate($data['expires_at'] ?? null);
        $manufacturedAt = $this->nullableDate($data['manufactured_at'] ?? null);

        return DB::transaction(function () use ($product, $data, $quantity, $warehouse, $lotNumber, $unitCost, $expiresAt, $manufacturedAt): Batch {
            $existing = Batch::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('lot_number', $lotNumber)
                ->first();

            $batch = $existing ?? Batch::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'lot_number' => $lotNumber,
                'manufactured_at' => $manufacturedAt,
                'expires_at' => $expiresAt,
                'quantity_on_hand' => 0,
                'unit_cost' => $unitCost,
                'currency_code' => $product->currency_code ?: 'CDF',
                'status' => Batch::STATUS_ACTIVE,
            ]);

            $this->stockMutator->mutate([
                'tenant_id' => $product->tenant_id,
                'batch_id' => (string) $batch->id,
                'type' => StockMovement::TYPE_IN_ADJUSTMENT,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => Product::class,
                'reference_id' => $product->id,
                'user_id' => $data['user_id'] ?? auth()->id(),
                'notes' => $data['notes'] ?? 'Stock initial à la création du produit',
            ]);

            return $batch->refresh();
        });
    }

    private function resolveWarehouse(Product $product, ?string $warehouseId): Warehouse
    {
        if ($warehouseId) {
            $warehouse = Warehouse::query()
                ->where('tenant_id', $product->tenant_id)
                ->whereKey($warehouseId)
                ->first();

            if (! $warehouse) {
                throw new OpeningStockException('Dépôt introuvable pour ce produit.');
            }

            return $warehouse;
        }

        $warehouse = Warehouse::query()
            ->where('tenant_id', $product->tenant_id)
            ->where('is_default', true)
            ->first()
            ?? Warehouse::query()->where('tenant_id', $product->tenant_id)->first();

        if (! $warehouse) {
            throw new OpeningStockException('Aucun dépôt actif. Créez un dépôt avant d’importer le stock.');
        }

        return $warehouse;
    }

    private function normalizeQuantity(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            return '0.000';
        }

        return is_string($quantity)
            ? $quantity
            : number_format((float) $quantity, 3, '.', '');
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return FlexibleDate::toDateString($value);
    }
}
