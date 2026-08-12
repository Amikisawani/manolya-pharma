<?php

namespace App\Domain\Reporting\Services;

use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

final class OperationalSpreadsheetExport
{
    public function exportSalesToFile(string $path, ?string $from = null, ?string $to = null, ?string $q = null): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'number', 'completed_at', 'status', 'cashier', 'subtotal', 'discount_total',
            'grand_total', 'profit_total', 'currency_code', 'payment_methods',
        ]));

        $this->salesQuery($from, $to, $q)
            ->with(['cashier:id,name', 'payments'])
            ->orderByDesc('completed_at')
            ->chunk(200, function ($sales) use ($writer): void {
                foreach ($sales as $sale) {
                    /** @var Sale $sale */
                    $methods = $sale->payments->pluck('method')->unique()->implode('+');
                    $writer->addRow(Row::fromValues([
                        $sale->number,
                        optional($sale->completed_at)?->toDateTimeString(),
                        $sale->status,
                        $sale->cashier?->name,
                        (string) $sale->subtotal,
                        (string) $sale->discount_total,
                        (string) $sale->grand_total,
                        (string) $sale->profit_total,
                        $sale->currency_code,
                        $methods,
                    ]));
                }
            });

        $writer->close();
    }

    public function exportMovementsToFile(string $path, ?string $from = null, ?string $to = null): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'created_at', 'type', 'product_sku', 'product_name', 'lot_number',
            'quantity', 'unit_cost', 'reference_type', 'reference_id', 'notes',
        ]));

        StockMovement::query()
            ->with([
                'product:id,sku,commercial_name',
                'batch:id,lot_number',
            ])
            ->when($from, fn ($q) => $q->whereDate('occurred_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('occurred_at', '<=', $to))
            ->orderByDesc('occurred_at')
            ->chunk(200, function ($movements) use ($writer): void {
                foreach ($movements as $m) {
                    /** @var StockMovement $m */
                    $writer->addRow(Row::fromValues([
                        optional($m->occurred_at)?->toDateTimeString(),
                        $m->type,
                        $m->product?->sku,
                        $m->product?->commercial_name,
                        $m->batch?->lot_number,
                        (string) $m->quantity,
                        (string) $m->unit_cost,
                        $m->reference_type,
                        $m->reference_id,
                        $m->notes,
                    ]));
                }
            });

        $writer->close();
    }

    private function salesQuery(?string $from, ?string $to, ?string $q): Builder
    {
        return Sale::query()
            ->when($q, fn ($query) => $query->where('number', 'like', "%{$q}%"))
            ->when($from, fn ($query) => $query->whereDate('completed_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('completed_at', '<=', $to));
    }
}
