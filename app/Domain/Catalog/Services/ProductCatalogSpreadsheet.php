<?php

namespace App\Domain\Catalog\Services;

use App\Models\Category;
use App\Models\Product;
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
        ];
    }

    public function exportToFile(string $path, string $format = 'xlsx'): void
    {
        $writer = $this->makeWriter($format);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        Product::query()
            ->with('category:id,name')
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
                    ]));
                }
            });

        $writer->close();
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importFromFile(string $path, string $tenantId, string $format = 'xlsx'): array
    {
        $reader = $this->makeReader($format);
        $reader->open($path);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        $headerMap = null;
        $line = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $values = array_map(
                    static fn ($v) => is_scalar($v) || $v === null ? trim((string) $v) : '',
                    $row->toArray()
                );

                if ($headerMap === null) {
                    $headerMap = $this->resolveHeaderMap($values);
                    if ($this->looksLikeHeader($values)) {
                        continue;
                    }
                    // First row is data with positional columns
                }

                if ($this->rowEmpty($values)) {
                    continue;
                }

                try {
                    $payload = $this->toPayload($values, $headerMap, $tenantId);
                    if ($payload === null) {
                        $skipped++;
                        continue;
                    }

                    $existing = Product::query()->where('sku', $payload['sku'])->first();
                    if ($existing) {
                        $existing->update($payload);
                        $updated++;
                    } else {
                        Product::query()->create($payload);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Ligne {$line} : ".$e->getMessage();
                    if (count($errors) >= 25) {
                        break 2;
                    }
                }
            }
            break;
        }

        $reader->close();

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

        return [
            'tenant_id' => $tenantId,
            'category_id' => $categoryId,
            'sku' => $sku,
            'commercial_name' => $name,
            'generic_name' => $get('generic_name') ?: null,
            'barcode' => $get('barcode') ?: null,
            'manufacturer' => $get('manufacturer') ?: null,
            'purchase_price' => $get('purchase_price', 0) ?: 0,
            'sale_price' => $get('sale_price', 0) ?: 0,
            'currency_code' => strtoupper((string) ($get('currency_code', 'CDF') ?: 'CDF')),
            'min_stock' => $get('min_stock', 0) ?: 0,
            'critical_stock' => $get('critical_stock', 0) ?: 0,
            'allocation_strategy' => $strategy,
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
}
