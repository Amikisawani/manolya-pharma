<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Inventory\Services\StockMutator;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class InvoicePurchaseImporter
{
    public const DEFAULT_DATASET = 'manolya_invoices_2026-09-22.json';

    public const DEFAULT_MARKUP = 1.4;

    public const DEFAULT_PURCHASED_AT = '2026-09-22';

    public const DEFAULT_EXPIRES_AT = '2027-09-22';

    public function __construct(
        private readonly StockMutator $stockMutator,
    ) {}

    public static function defaultDatasetPath(): string
    {
        return database_path('data/'.self::DEFAULT_DATASET);
    }

    /**
     * @param  array{
     *     markup?: float|int|string,
     *     expires_at?: string,
     *     purchased_at?: string,
     *     dry_run?: bool,
     *     user_id?: string|null,
     *     warehouse_id?: string|null
     * }  $options
     * @return array{
     *     products_created: int,
     *     products_reused: int,
     *     batches_created: int,
     *     batches_skipped: int,
     *     lines_skipped: int,
     *     suppliers_created: int,
     *     errors: list<string>,
     *     warnings: list<string>
     * }
     */
    public function import(string $path, Tenant $tenant, array $options = []): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Fichier introuvable ou illisible : {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Le fichier JSON est invalide.');
        }

        $markup = (float) ($options['markup'] ?? self::DEFAULT_MARKUP);
        if ($markup <= 0) {
            throw new InvalidArgumentException('Le coefficient de marge doit être supérieur à 0.');
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $tz = $tenant->timezone ?: 'Africa/Kinshasa';
        $purchasedAt = Carbon::parse((string) ($options['purchased_at'] ?? self::DEFAULT_PURCHASED_AT), $tz)->startOfDay();
        $expiresAt = Carbon::parse((string) ($options['expires_at'] ?? self::DEFAULT_EXPIRES_AT), $tz)->toDateString();

        app()->instance('current_tenant_id', (string) $tenant->id);

        $warehouse = $this->resolveWarehouse($tenant, $options['warehouse_id'] ?? null);
        $userId = $this->resolveUserId($tenant, $options['user_id'] ?? null);
        $currency = $tenant->default_currency ?: 'CDF';

        $stats = [
            'products_created' => 0,
            'products_reused' => 0,
            'batches_created' => 0,
            'batches_skipped' => 0,
            'lines_skipped' => 0,
            'suppliers_created' => 0,
            'errors' => [],
            'warnings' => [
                "Péremption facture absente : lots créés avec date fictive {$expiresAt} (à corriger dès que les dates réelles sont connues).",
                'Prix de vente estimé = prix d’achat × '.rtrim(rtrim(number_format($markup, 2, '.', ''), '0'), '.').' (arrondi à l’unité).',
            ],
        ];

        $lotOccurrences = [];

        foreach ($decoded as $index => $row) {
            $lineNo = $index + 1;

            if (! is_array($row) || ($row['_meta'] ?? false) === true) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $qty = $this->normalizeQuantity($row['qty'] ?? 0);

            if ($name === '' || bccomp($qty, '0', 3) <= 0) {
                $stats['lines_skipped']++;

                continue;
            }

            $supplierName = trim((string) ($row['supplier'] ?? ''));
            $invoice = trim((string) ($row['invoice'] ?? ''));
            $categoryName = trim((string) ($row['category'] ?? 'Autre')) ?: 'Autre';
            $pack = trim((string) ($row['pack'] ?? ''));
            $unitCost = $this->normalizeMoney($row['unit_cost'] ?? 0);

            if ($invoice === '' || $supplierName === '') {
                $stats['lines_skipped']++;
                $stats['errors'][] = "Ligne {$lineNo} : fournisseur ou n° de facture manquant.";

                continue;
            }

            if (bccomp($unitCost, '0', 2) === 0) {
                $stats['warnings'][] = "Ligne {$lineNo} ({$name}) : coût 0 (gratuit / OCR) — stock quand même reçu.";
            }

            if ($dryRun) {
                $existing = Product::query()
                    ->whereRaw('LOWER(commercial_name) = ?', [mb_strtolower($name)])
                    ->first();
                $existing ? $stats['products_reused']++ : $stats['products_created']++;
                $stats['batches_created']++;

                continue;
            }

            try {
                $result = DB::transaction(function () use (
                    $tenant,
                    $warehouse,
                    $userId,
                    $currency,
                    $name,
                    $qty,
                    $supplierName,
                    $invoice,
                    $categoryName,
                    $pack,
                    $unitCost,
                    $markup,
                    $purchasedAt,
                    $expiresAt,
                    &$lotOccurrences,
                ): array {
                    $supplier = $this->firstOrCreateSupplier($tenant, $supplierName);
                    $category = Category::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => $categoryName],
                        ['tenant_id' => $tenant->id, 'name' => $categoryName],
                    );

                    $product = $this->matchOrCreateProduct(
                        $tenant,
                        $name,
                        $category->id,
                        $supplier->id,
                        $unitCost,
                        $markup,
                        $currency,
                    );

                    $occurrenceKey = mb_strtolower($name).'|'.$invoice;
                    $lotOccurrences[$occurrenceKey] = ($lotOccurrences[$occurrenceKey] ?? 0) + 1;
                    $lotNumber = $this->lotNumber($invoice, $product->sku, $lotOccurrences[$occurrenceKey]);

                    $existingLot = Batch::query()
                        ->where('product_id', $product->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('lot_number', $lotNumber)
                        ->first();

                    if ($existingLot) {
                        return [
                            'product_created' => false,
                            'batch_created' => false,
                            'supplier_created' => $supplier->wasRecentlyCreated,
                        ];
                    }

                    $batch = Batch::query()->create([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                        'lot_number' => $lotNumber,
                        'manufactured_at' => null,
                        'expires_at' => $expiresAt,
                        'quantity_on_hand' => 0,
                        'unit_cost' => $unitCost,
                        'currency_code' => $currency,
                        'status' => Batch::STATUS_ACTIVE,
                    ]);

                    $this->stockMutator->mutate([
                        'tenant_id' => $tenant->id,
                        'batch_id' => $batch->id,
                        'type' => StockMovement::TYPE_IN_PURCHASE,
                        'quantity' => $qty,
                        'unit_cost' => $unitCost,
                        'reference_type' => self::class,
                        'reference_id' => null,
                        'user_id' => $userId,
                        'notes' => trim("Facture {$invoice} — {$supplierName}".($pack !== '' ? " ({$pack})" : '')),
                        'occurred_at' => $purchasedAt,
                    ]);

                    return [
                        'product_created' => $product->wasRecentlyCreated,
                        'batch_created' => true,
                        'supplier_created' => $supplier->wasRecentlyCreated,
                    ];
                });

                $result['product_created'] ? $stats['products_created']++ : $stats['products_reused']++;
                $result['batch_created'] ? $stats['batches_created']++ : $stats['batches_skipped']++;
                if ($result['supplier_created']) {
                    $stats['suppliers_created']++;
                }
            } catch (\Throwable $e) {
                $stats['errors'][] = "Ligne {$lineNo} ({$name}) : ".$e->getMessage();
                if (count($stats['errors']) >= 25) {
                    break;
                }
            }
        }

        return $stats;
    }

    private function resolveWarehouse(Tenant $tenant, mixed $warehouseId): Warehouse
    {
        $query = Warehouse::query()->where('tenant_id', $tenant->id);

        if (is_string($warehouseId) && $warehouseId !== '') {
            $warehouse = (clone $query)
                ->where(function ($inner) use ($warehouseId): void {
                    $inner->whereKey($warehouseId)->orWhere('code', $warehouseId);
                })
                ->first();

            if ($warehouse === null) {
                throw new RuntimeException("Dépôt introuvable : {$warehouseId}");
            }

            return $warehouse;
        }

        $warehouse = (clone $query)->where('is_default', true)->first()
            ?? $query->first();

        if ($warehouse === null) {
            throw new RuntimeException('Aucun dépôt actif. Créez un dépôt avant d’importer les factures.');
        }

        return $warehouse;
    }

    private function resolveUserId(Tenant $tenant, mixed $userId): ?string
    {
        if (is_string($userId) && $userId !== '') {
            return $userId;
        }

        $owner = User::query()
            ->where('tenant_id', $tenant->id)
            ->role('owner')
            ->first();

        return $owner?->id;
    }

    private function firstOrCreateSupplier(Tenant $tenant, string $name): Supplier
    {
        $existing = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Supplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => $this->uniqueSupplierCode($tenant, $name),
        ]);
    }

    private function uniqueSupplierCode(Tenant $tenant, string $name): string
    {
        $known = [
            'compagnon epvg' => 'COMPAGNON',
            'avril pharma depot gombe' => 'AVRIL',
            'pharmans' => 'PHARMANS',
            'unique depot pharmaceutique' => 'UNIQUE',
        ];

        $base = $known[mb_strtolower($name)]
            ?? Str::upper(Str::slug(Str::before($name, ' '), ''));
        $base = Str::limit($base !== '' ? $base : 'FOUR', 16, '');

        $code = $base;
        $i = 2;
        while (Supplier::query()->where('tenant_id', $tenant->id)->where('code', $code)->exists()) {
            $code = Str::limit($base, 14, '').$i;
            $i++;
        }

        return $code;
    }

    private function matchOrCreateProduct(
        Tenant $tenant,
        string $name,
        string $categoryId,
        string $supplierId,
        string $unitCost,
        float $markup,
        string $currency,
    ): Product {
        $product = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', [mb_strtolower($name)])
            ->first();

        $salePrice = $this->suggestedSalePrice($unitCost, $markup);

        if ($product === null) {
            return Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $categoryId,
                'sku' => $this->uniqueSku($tenant, $name),
                'commercial_name' => $name,
                'preferred_supplier_id' => $supplierId,
                'purchase_price' => $unitCost,
                'sale_price' => $salePrice,
                'currency_code' => $currency,
                'min_stock' => 0,
                'critical_stock' => 0,
                'allocation_strategy' => 'fefo',
            ]);
        }

        $updates = [
            'preferred_supplier_id' => $supplierId,
            'category_id' => $product->category_id ?: $categoryId,
        ];

        if (bccomp($unitCost, '0', 2) > 0) {
            $updates['purchase_price'] = $unitCost;
            if (bccomp((string) $product->sale_price, '0', 2) <= 0) {
                $updates['sale_price'] = $salePrice;
            }
        }

        $product->fill($updates);
        $product->save();

        return $product;
    }

    private function uniqueSku(Tenant $tenant, string $name): string
    {
        $normalized = str_replace(['/', '\\'], '-', $name);
        $base = Str::upper(Str::slug($normalized, '-'));
        $base = Str::limit($base !== '' ? $base : 'MED', 48, '');

        $sku = $base;
        $i = 2;
        while (Product::query()->where('tenant_id', $tenant->id)->where('sku', $sku)->exists()) {
            $sku = Str::limit($base, 45, '').'-'.$i;
            $i++;
        }

        return $sku;
    }

    private function lotNumber(string $invoice, string $sku, int $occurrence): string
    {
        $base = 'ACH-'.$invoice.'-'.Str::limit($sku, 24, '');
        $lot = $occurrence <= 1 ? $base : $base.'-'.$occurrence;

        return Str::limit($lot, 64, '');
    }

    private function suggestedSalePrice(string $unitCost, float $markup): string
    {
        if (bccomp($unitCost, '0', 2) <= 0) {
            return '0.00';
        }

        return number_format(round((float) $unitCost * $markup, 0), 2, '.', '');
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

    private function normalizeMoney(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        return is_string($amount)
            ? $amount
            : number_format((float) $amount, 2, '.', '');
    }
}
