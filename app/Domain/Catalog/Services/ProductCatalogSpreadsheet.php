<?php

namespace App\Domain\Catalog\Services;

use App\Casts\TrimmedDecimal;
use App\Domain\Inventory\Services\StockMutator;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvReaderOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Options as CsvWriterOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

final class ProductCatalogSpreadsheet
{
    /**
     * Canonical English headers (export).
     *
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'sku',
            'commercial_name',
            'generic_name',
            'barcode',
            'manufacturer',
            'purchase_price',
            'sale_price',
            'currency_code',
            'min_stock',
            'critical_stock',
            'allocation_strategy',
            'category',
            'preferred_supplier',
            'description',
        ];
    }

    /**
     * Map normalized header aliases → canonical column.
     *
     * @return array<string, string>
     */
    private function headerAliases(): array
    {
        return [
            'sku' => 'sku',
            'code' => 'sku',
            'code_sku' => 'sku',
            'ref' => 'sku',
            'reference' => 'sku',

            'commercial_name' => 'commercial_name',
            'nom_commercial' => 'commercial_name',
            'nom' => 'commercial_name',
            'produit' => 'commercial_name',
            'name' => 'commercial_name',
            'libelle' => 'commercial_name',
            'libellé' => 'commercial_name',

            'generic_name' => 'generic_name',
            'dci' => 'generic_name',
            'dci_/_generique' => 'generic_name',
            'dci_/_générique' => 'generic_name',
            'generique' => 'generic_name',
            'générique' => 'generic_name',
            'dci_generique' => 'generic_name',
            'dci_générique' => 'generic_name',

            'barcode' => 'barcode',
            'code_barres' => 'barcode',
            'codebarre' => 'barcode',
            'ean' => 'barcode',

            'manufacturer' => 'manufacturer',
            'fabriquant' => 'manufacturer',
            'fabricant' => 'manufacturer',
            'laboratoire' => 'manufacturer',

            'purchase_price' => 'purchase_price',
            'prix_achat' => 'purchase_price',
            'prix_dachat' => 'purchase_price',
            'prix_d_achat' => 'purchase_price',
            "prix_d'achat" => 'purchase_price',
            'pa' => 'purchase_price',

            'sale_price' => 'sale_price',
            'prix_vente' => 'sale_price',
            'prix_de_vente' => 'sale_price',
            'pv' => 'sale_price',

            'currency_code' => 'currency_code',
            'devise' => 'currency_code',
            'currency' => 'currency_code',

            'min_stock' => 'min_stock',
            'stock_min' => 'min_stock',
            'stock_minimum' => 'min_stock',

            'critical_stock' => 'critical_stock',
            'stock_critique' => 'critical_stock',

            'allocation_strategy' => 'allocation_strategy',
            'strategie_dallocation' => 'allocation_strategy',
            'strategie_d_allocation' => 'allocation_strategy',
            "strategie_d'allocation" => 'allocation_strategy',
            'stratégie_dallocation' => 'allocation_strategy',
            "stratégie_d'allocation" => 'allocation_strategy',
            'strategie' => 'allocation_strategy',
            'fefo_fifo' => 'allocation_strategy',

            'category' => 'category',
            'categorie' => 'category',
            'catégorie' => 'category',
            'cat' => 'category',

            'preferred_supplier' => 'preferred_supplier',
            'fournisseur' => 'preferred_supplier',
            'fournisseur_prefere' => 'preferred_supplier',
            'fournisseur_préféré' => 'preferred_supplier',
            'supplier' => 'preferred_supplier',

            'description' => 'description',
            'desc' => 'description',
            'commentaire' => 'description',
            'notes' => 'description',

            'initial_qty' => 'initial_qty',
            'quantite' => 'initial_qty',
            'quantite_initiale' => 'initial_qty',
            'qte' => 'initial_qty',
            'qte_stock' => 'initial_qty',
            'stock_initial' => 'initial_qty',
            'stock' => 'initial_qty',
            'qty' => 'initial_qty',

            'lot_number' => 'lot_number',
            'lot' => 'lot_number',
            'numero_lot' => 'lot_number',
            'n_lot' => 'lot_number',

            'expires_at' => 'expires_at',
            'expiration' => 'expires_at',
            'date_expiration' => 'expires_at',
            'peremption' => 'expires_at',
            'péremption' => 'expires_at',
        ];
    }

    public function exportToFile(string $path, string $format = 'xlsx'): void
    {
        $writer = $this->makeWriter($format);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        Product::query()
            ->with(['category:id,name', 'preferredSupplier:id,name'])
            ->orderBy('commercial_name')
            ->chunk(200, function ($products) use ($writer): void {
                foreach ($products as $product) {
                    $writer->addRow(Row::fromValues([
                        $product->sku,
                        $product->commercial_name,
                        $product->generic_name,
                        $product->barcode,
                        $product->manufacturer,
                        (string) $product->purchase_price,
                        (string) $product->sale_price,
                        $product->currency_code,
                        (string) $product->min_stock,
                        (string) $product->critical_stock,
                        $product->allocation_strategy,
                        $product->category?->name,
                        $product->preferredSupplier?->name,
                        $product->description,
                    ]));
                }
            });

        $writer->close();
    }

    /**
     * @return array{created: int, updated: int, stocked: int, skipped: int, errors: list<string>}
     */
    public function importFromFile(string $path, string $tenantId, string $format = 'xlsx'): array
    {
        $reader = $this->makeReader($format);
        $reader->open($path);

        $created = 0;
        $updated = 0;
        $stocked = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        $headerMap = null;
        $line = 0;
        $warehouse = $this->defaultWarehouse($tenantId);
        $mutator = app(StockMutator::class);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $values = array_map(
                    static fn ($v) => is_scalar($v) || $v === null ? trim((string) $v) : '',
                    $row->toArray()
                );

                if ($this->rowEmpty($values)) {
                    continue;
                }

                if ($headerMap === null) {
                    if ($this->looksLikeHeader($values)) {
                        $headerMap = $this->resolveHeaderMap($values);
                        if (! isset($headerMap['sku'], $headerMap['commercial_name'])) {
                            $errors[] = "Ligne {$line} : en-têtes incomplets (colonnes SKU et nom commercial obligatoires).";
                            break 2;
                        }
                        continue;
                    }

                    // No header row → positional English layout
                    $headerMap = $this->positionalMap();
                }

                if ($this->looksLikeHeader($values)) {
                    $skipped++;
                    continue;
                }

                try {
                    DB::transaction(function () use ($values, $headerMap, $tenantId, $warehouse, $mutator, &$created, &$updated, &$stocked, &$skipped): void {
                        $payload = $this->toPayload($values, $headerMap, $tenantId);
                        if ($payload === null) {
                            $skipped++;

                            return;
                        }

                        $stockMeta = $this->stockMetaFromRow($values, $headerMap, $payload);

                        $existing = Product::query()->where('sku', $payload['sku'])->first();
                        if ($existing) {
                            $existing->update($payload);
                            $product = $existing->fresh();
                            $updated++;
                        } else {
                            $product = Product::query()->create($payload);
                            $created++;
                        }

                        if ($this->ensureOpeningStock($product, $warehouse, $stockMeta, $mutator)) {
                            $stocked++;
                        }
                    });
                } catch (\Throwable $e) {
                    $errors[] = "Ligne {$line} : ".$this->friendlyError($e);
                    if (count($errors) >= 25) {
                        break 2;
                    }
                }
            }
            break;
        }

        $reader->close();

        return compact('created', 'updated', 'stocked', 'skipped', 'errors');
    }

    /**
     * Create opening stock for catalogue products that have no batch yet (e.g. past imports).
     *
     * @return array{stocked: int, skipped: int}
     */
    public function seedMissingStockForTenant(string $tenantId): array
    {
        $warehouse = $this->defaultWarehouse($tenantId);
        $mutator = app(StockMutator::class);
        $stocked = 0;
        $skipped = 0;

        Product::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sku')
            ->chunkById(100, function ($products) use ($warehouse, $mutator, &$stocked, &$skipped): void {
                foreach ($products as $product) {
                    $meta = [
                        'qty' => $this->defaultOpeningQty((string) $product->min_stock, (string) $product->critical_stock),
                        'lot_number' => 'IMP-'.Str::upper(Str::substr((string) $product->sku, 0, 16)),
                        'expires_at' => Carbon::today()->addMonths(18)->toDateString(),
                    ];

                    if ($this->ensureOpeningStock($product, $warehouse, $meta, $mutator)) {
                        $stocked++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return compact('stocked', 'skipped');
    }

    private function defaultWarehouse(string $tenantId): Warehouse
    {
        $warehouse = Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first()
            ?? Warehouse::query()->where('tenant_id', $tenantId)->orderBy('name')->first();

        if (! $warehouse) {
            throw new \RuntimeException('Aucun entrepôt trouvé pour ce tenant — créez un entrepôt avant d’importer le stock.');
        }

        return $warehouse;
    }

    /**
     * @param  array<string, int>  $map
     * @param  array<string, mixed>  $payload
     * @return array{qty: string, lot_number: string, expires_at: string}
     */
    private function stockMetaFromRow(array $values, array $map, array $payload): array
    {
        $get = fn (string $key, mixed $default = null) => $values[$map[$key] ?? -1] ?? $default;

        $qtyRaw = trim((string) $get('initial_qty', ''));
        $qty = $qtyRaw !== ''
            ? $this->toDecimal($qtyRaw, 'quantité stock', 3)
            : $this->defaultOpeningQty((string) $payload['min_stock'], (string) $payload['critical_stock']);

        $lot = trim((string) $get('lot_number', ''));
        if ($lot === '') {
            $lot = 'IMP-'.Str::upper(Str::substr((string) $payload['sku'], 0, 16));
        }

        $expires = trim((string) $get('expires_at', ''));
        if ($expires === '') {
            $expires = Carbon::today()->addMonths(18)->toDateString();
        } else {
            try {
                $expires = Carbon::parse($expires)->toDateString();
            } catch (\Throwable) {
                $expires = Carbon::today()->addMonths(18)->toDateString();
            }
        }

        return [
            'qty' => $qty,
            'lot_number' => $lot,
            'expires_at' => $expires,
        ];
    }

    private function defaultOpeningQty(string $minStock, string $criticalStock): string
    {
        $min = (float) $minStock;
        $critical = (float) $criticalStock;
        $qty = max($min * 2, $critical * 3, 50);

        return TrimmedDecimal::format((string) $qty, 3);
    }

    /**
     * @param  array{qty: string, lot_number: string, expires_at: string}  $meta
     */
    private function ensureOpeningStock(Product $product, Warehouse $warehouse, array $meta, StockMutator $mutator): bool
    {
        if (bccomp($meta['qty'], '0', 3) <= 0) {
            return false;
        }

        $hasStock = Batch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_on_hand', '>', 0)
            ->exists();

        if ($hasStock) {
            return false;
        }

        $batch = Batch::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => $meta['lot_number'],
            'manufactured_at' => Carbon::today()->subMonths(1)->toDateString(),
            'expires_at' => $meta['expires_at'],
            'quantity_on_hand' => 0,
            'unit_cost' => $product->purchase_price ?? '0',
            'currency_code' => $product->currency_code ?: 'CDF',
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $mutator->mutate([
            'tenant_id' => (string) $product->tenant_id,
            'batch_id' => (string) $batch->id,
            'type' => StockMovement::TYPE_IN_ADJUSTMENT,
            'quantity' => $meta['qty'],
            'unit_cost' => $product->purchase_price ?? '0',
            'notes' => 'Stock initial (import catalogue)',
        ]);

        return true;
    }

    private function friendlyError(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'Invalid text representation') || str_contains($message, 'numeric')) {
            return 'valeur numérique invalide (vérifiez prix / stocks — la ligne d’en-tête a peut‑être été lue comme donnée).';
        }

        return $message;
    }

    private function makeWriter(string $format): WriterInterface
    {
        if (strtolower($format) === 'csv') {
            $options = new CsvWriterOptions;
            $options->FIELD_DELIMITER = ';';
            $options->FIELD_ENCLOSURE = '"';
            $options->SHOULD_ADD_BOM = true;

            return new CsvWriter($options);
        }

        return new XlsxWriter;
    }

    private function makeReader(string $format): ReaderInterface
    {
        $format = strtolower($format);
        if (in_array($format, ['csv', 'txt'], true)) {
            $options = new CsvReaderOptions;
            $options->FIELD_DELIMITER = ';';
            $options->FIELD_ENCLOSURE = '"';

            return new CsvReader($options);
        }

        return new XlsxReader;
    }

    /**
     * @param  list<string>  $values
     * @return array<string, int>
     */
    private function resolveHeaderMap(array $values): array
    {
        $aliases = $this->headerAliases();
        $map = [];

        foreach ($values as $idx => $raw) {
            $key = $this->normalizeHeader((string) $raw);
            if ($key === '' || ! isset($aliases[$key])) {
                continue;
            }
            $canonical = $aliases[$key];
            if (! isset($map[$canonical])) {
                $map[$canonical] = (int) $idx;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function positionalMap(): array
    {
        $map = [];
        foreach ($this->headers() as $i => $col) {
            $map[$col] = $i;
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = str_replace(
            ['é', 'è', 'ê', 'à', 'â', 'î', 'ô', 'ù', 'û', 'ç', '’', "'"],
            ['e', 'e', 'e', 'a', 'a', 'i', 'o', 'u', 'u', 'c', '_', '_'],
            $header
        );
        $header = preg_replace('/[^a-z0-9]+/u', '_', $header) ?? $header;

        return trim($header, '_');
    }

    /**
     * @param  list<string>  $values
     */
    private function looksLikeHeader(array $values): bool
    {
        $joined = $this->normalizeHeader(implode(' ', array_slice($values, 0, 8)));
        $first = $this->normalizeHeader((string) ($values[0] ?? ''));

        if (in_array($first, ['sku', 'nom_commercial', 'commercial_name', 'nom', 'produit'], true)) {
            return true;
        }

        return str_contains($joined, 'sku')
            || str_contains($joined, 'nom_commercial')
            || str_contains($joined, 'prix_achat')
            || str_contains($joined, 'prix_d_achat')
            || str_contains($joined, 'prix_vente')
            || str_contains($joined, 'purchase_price')
            || str_contains($joined, 'stock_min')
            || str_contains($joined, 'stock_critique');
    }

    /**
     * @param  list<string>  $values
     * @param  array<string, int>  $map
     * @return array<string, mixed>|null
     */
    private function toPayload(array $values, array $map, string $tenantId): ?array
    {
        $get = fn (string $key, mixed $default = null) => $values[$map[$key] ?? -1] ?? $default;

        $sku = trim((string) $get('sku', ''));
        $name = trim((string) $get('commercial_name', ''));
        if ($sku === '' || $name === '') {
            return null;
        }

        // Guard: never persist header-like rows
        if ($this->looksLikeHeader([$sku, $name])) {
            return null;
        }

        $strategy = strtolower((string) $get('allocation_strategy', 'fefo')) ?: 'fefo';
        if (! in_array($strategy, ['fefo', 'fifo', 'lifo'], true)) {
            $strategy = 'fefo';
        }

        $categoryName = trim((string) $get('category', ''));
        $categoryId = null;
        if ($categoryName !== '') {
            $category = Category::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $categoryName],
                ['tenant_id' => $tenantId, 'name' => $categoryName],
            );
            $categoryId = $category->id;
        }

        $supplierName = trim((string) $get('preferred_supplier', ''));
        $supplierId = null;
        if ($supplierName !== '') {
            $supplier = Supplier::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $supplierName],
                [
                    'tenant_id' => $tenantId,
                    'name' => $supplierName,
                    'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $supplierName) ?: 'SUP', 0, 12)),
                ],
            );
            $supplierId = $supplier->id;
        }

        return [
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'preferred_supplier_id' => $supplierId,
            'sku' => $sku,
            'commercial_name' => $name,
            'generic_name' => $get('generic_name') ?: null,
            'barcode' => $get('barcode') ?: null,
            'manufacturer' => $get('manufacturer') ?: null,
            'purchase_price' => $this->toDecimal($get('purchase_price', 0), 'prix d’achat', 2),
            'sale_price' => $this->toDecimal($get('sale_price', 0), 'prix de vente', 2),
            'currency_code' => strtoupper((string) ($get('currency_code', 'CDF') ?: 'CDF')),
            'min_stock' => $this->toDecimal($get('min_stock', 0), 'stock min', 3),
            'critical_stock' => $this->toDecimal($get('critical_stock', 0), 'stock critique', 3),
            'allocation_strategy' => $strategy,
            'description' => $get('description') ?: null,
        ];
    }

    private function toDecimal(mixed $value, string $label, int $scale = 3): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_numeric($value)) {
            return TrimmedDecimal::format((string) $value, $scale);
        }

        $raw = trim((string) $value);
        $raw = str_replace([' ', "\u{00A0}", 'Fc', 'CDF', '€', '$'], '', $raw);
        $raw = str_replace(',', '.', $raw);

        if (! is_numeric($raw)) {
            throw new \InvalidArgumentException("« {$label} » invalide : {$value}");
        }

        return TrimmedDecimal::format($raw, $scale);
    }

    /**
     * @param  list<string>  $values
     */
    private function rowEmpty(array $values): bool
    {
        foreach ($values as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }
}
