<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\SampleMedicationCatalog;
use App\Domain\Inventory\Services\OpeningStockService;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Support\FlexibleDate;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
    public function __construct(
        private readonly OpeningStockService $openingStock,
    ) {}

    /**
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
            'description',
            'supplier',
            'initial_qty',
            'lot_number',
            'expires_at',
            'warehouse',
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
                        $product->description,
                        $product->preferredSupplier?->name,
                        '',
                        '',
                        '',
                        '',
                    ]));
                }
            });

        $writer->close();
    }

    public function writeSampleTemplate(string $path, string $format = 'xlsx'): void
    {
        $writer = $this->makeWriter($format);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        foreach (SampleMedicationCatalog::rows() as $row) {
            $writer->addRow(Row::fromValues([
                $row['sku'],
                $row['commercial_name'],
                $row['generic_name'],
                $row['barcode'],
                $row['manufacturer'],
                $row['purchase_price'],
                $row['sale_price'],
                $row['currency_code'],
                $row['min_stock'],
                $row['critical_stock'],
                $row['allocation_strategy'],
                $row['category'],
                $row['description'],
                '',
                $row['initial_qty'],
                $row['lot_number'],
                $row['expires_at'],
                $row['warehouse'],
            ]));
        }

        $writer->close();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importFromFile(string $path, string $tenantId, string $format = 'xlsx'): array
    {
        if ($path === '' || ! is_readable($path)) {
            throw new \RuntimeException('Fichier Excel introuvable sur le serveur.');
        }

        $reader = $this->makeReader($format);
        $reader->open($path);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        $headerMap = null;
        $line = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $values = array_map(function ($v): string {
                        if ($v instanceof DateTimeInterface) {
                            return $v->format('Y-m-d');
                        }

                        return is_scalar($v) || $v === null ? trim((string) $v) : '';
                    }, $row->toArray());

                    if ($headerMap === null) {
                        $headerMap = $this->resolveHeaderMap($values);
                        if ($this->looksLikeHeader($values)) {
                            continue;
                        }
                    }

                    if ($this->rowEmpty($values)) {
                        continue;
                    }

                    try {
                        $status = DB::transaction(function () use ($values, $headerMap, $tenantId): string {
                            $payload = $this->toPayload($values, $headerMap, $tenantId);
                            if ($payload === null) {
                                return 'skipped';
                            }

                            $stock = $payload['stock'];
                            unset($payload['stock']);

                            $existing = Product::query()->where('sku', $payload['sku'])->first();
                            if ($existing) {
                                $existing->update($payload);
                                $product = $existing;
                                $result = 'updated';
                            } else {
                                $product = Product::query()->create($payload);
                                $result = 'created';
                            }

                            if ($stock !== null) {
                                $this->openingStock->receiveForProduct($product, $stock);
                            }

                            return $result;
                        });

                        if ($status === 'skipped') {
                            $skipped++;
                        } elseif ($status === 'created') {
                            $created++;
                        } else {
                            $updated++;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = "Ligne {$line} : ".$this->friendlyRowError($e);
                        if (count($errors) >= 25) {
                            break 2;
                        }
                    }
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return compact('created', 'updated', 'skipped', 'errors');
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
        $normalized = array_map(
            static fn (string $h) => strtolower(str_replace([' ', '-'], '_', trim($h))),
            $values
        );

        if ($this->looksLikeHeader($values)) {
            $map = [];
            foreach ($this->headers() as $col) {
                $idx = array_search($col, $normalized, true);
                if ($idx !== false) {
                    $map[$col] = (int) $idx;
                }
            }

            if (isset($map['sku'], $map['commercial_name'])) {
                return $map;
            }
        }

        // Positional map
        $map = [];
        foreach ($this->headers() as $i => $col) {
            $map[$col] = $i;
        }

        return $map;
    }

    /**
     * @param  list<string>  $values
     */
    private function looksLikeHeader(array $values): bool
    {
        $first = strtolower(str_replace([' ', '-'], '_', trim((string) ($values[0] ?? ''))));

        return $first === 'sku';
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

        $supplierName = trim((string) $get('supplier', ''));
        $supplierId = null;
        if ($supplierName !== '') {
            $supplier = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->where('name', $supplierName)
                ->first();
            if (! $supplier) {
                $supplier = Supplier::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => $supplierName,
                    'code' => 'S-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sku) ?: uniqid(), 0, 10)),
                ]);
            }
            $supplierId = $supplier->id;
        }

        $payload = [
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'sku' => $sku,
            'commercial_name' => $name,
            'generic_name' => $get('generic_name') ?: null,
            'barcode' => $get('barcode') ?: null,
            'manufacturer' => $get('manufacturer') ?: null,
            'preferred_supplier_id' => $supplierId,
            'purchase_price' => $get('purchase_price', 0) ?: 0,
            'sale_price' => $get('sale_price', 0) ?: 0,
            'currency_code' => strtoupper((string) ($get('currency_code', 'CDF') ?: 'CDF')),
            'min_stock' => $get('min_stock', 0) ?: 0,
            'critical_stock' => $get('critical_stock', 0) ?: 0,
            'allocation_strategy' => $strategy,
            'description' => $get('description') ?: null,
            'stock' => $this->stockPayload($get, $tenantId),
        ];

        return $payload;
    }

    /**
     * @param  callable(string, mixed=): mixed  $get
     * @return array<string, mixed>|null
     */
    private function stockPayload(callable $get, string $tenantId): ?array
    {
        $qty = trim((string) $get('initial_qty', ''));
        if ($qty === '' || ! is_numeric($qty) || (float) $qty <= 0) {
            return null;
        }

        $warehouseId = null;
        $warehouseKey = trim((string) $get('warehouse', ''));
        if ($warehouseKey !== '') {
            $warehouse = Warehouse::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($warehouseKey): void {
                    $query->where('code', $warehouseKey)
                        ->orWhere('name', $warehouseKey);
                })
                ->first();
            $warehouseId = $warehouse?->id;
        }

        return [
            'quantity' => $qty,
            'lot_number' => $get('lot_number') ?: null,
            'expires_at' => FlexibleDate::toDateString($get('expires_at') ?: null),
            'warehouse_id' => $warehouseId,
            'notes' => 'Stock initial import catalogue',
        ];
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

    private function friendlyRowError(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($e instanceof QueryException || str_contains($message, 'SQLSTATE')) {
            return 'données invalides pour cette ligne.';
        }

        if (mb_strlen($message) > 180) {
            return 'ligne ignorée (données invalides).';
        }

        return $message !== '' ? $message : 'ligne ignorée.';
    }
}
