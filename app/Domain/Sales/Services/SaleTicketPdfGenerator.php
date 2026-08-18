<?php

namespace App\Domain\Sales\Services;

use App\Casts\TrimmedDecimal;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;

final class SaleTicketPdfGenerator
{
    public function raw(Sale $sale): string
    {
        $sale->loadMissing([
            'tenant:id,name',
            'lines.product:id,commercial_name,sku',
            'lines.batch:id,lot_number',
            'payments',
            'cashier:id,name,email,phone',
            'site:id,name',
            'warehouse:id,name',
            'cashRegisterSession:id,number',
        ]);

        $payload = $this->payload($sale);

        // 58 mm = 164.41 pt. Hauteur rouleau généreuse pour le PT-210.
        return Pdf::loadView('sales.ticket', ['ticket' => $payload])
            ->setPaper([0, 0, 164.41, 1134], 'portrait')
            ->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Sale $sale): array
    {
        $methodLabels = [
            'cash' => 'Espèces',
            'card' => 'Carte',
            'mobile_money' => 'Mobile Money',
        ];

        return [
            'number' => $sale->number,
            'completed_at' => optional($sale->completed_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—',
            'currency' => $sale->currency_code ?: 'CDF',
            'tenant' => $sale->tenant?->name ?? 'Manolya Pharma',
            'cashier' => [
                'name' => $sale->cashier?->name,
                'email' => $sale->cashier?->email,
                'phone' => $sale->cashier?->phone,
            ],
            'site' => $sale->site?->name,
            'warehouse' => $sale->warehouse?->name,
            'session' => $sale->cashRegisterSession?->number,
            'lines' => $sale->lines->map(static function ($line) {
                return [
                    'name' => $line->product?->commercial_name ?? 'Article',
                    'sku' => $line->product?->sku,
                    'lot' => $line->batch?->lot_number,
                    'qty' => TrimmedDecimal::format((string) $line->quantity, 3),
                    'qty_returned' => TrimmedDecimal::format((string) ($line->quantity_returned ?? 0), 3),
                    'unit_price' => (string) $line->unit_price,
                    'line_total' => (string) $line->line_total,
                ];
            })->all(),
            'subtotal' => (string) $sale->subtotal,
            'discount_total' => (string) $sale->discount_total,
            'grand_total' => (string) $sale->grand_total,
            'payments' => $sale->payments->map(static function ($payment) use ($methodLabels) {
                return [
                    'method' => $methodLabels[$payment->method] ?? $payment->method,
                    'provider' => $payment->provider,
                    'amount' => (string) $payment->amount,
                ];
            })->all(),
            'generated_at' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }
}
