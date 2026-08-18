<?php

namespace App\Domain\Sales\Receipts;

use App\Domain\Shared\Formatting\MoneyFormatter;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalePayment;

final class ThermalReceiptBuilder
{
    public function __construct(
        private readonly MoneyFormatter $money,
    ) {}

    public function fromSale(Sale $sale, bool $isReprint = false): ThermalReceipt
    {
        $sale->loadMissing([
            'lines.product:id,commercial_name,sku',
            'lines.batch:id,lot_number',
            'payments',
            'cashier:id,name',
            'site:id,name,address',
            'tenant:id,name,timezone',
            'cashRegisterSession:id,number',
        ]);

        $symbol = config('currency.symbol', 'Fc');
        $timezone = $sale->tenant?->timezone ?: config('app.timezone', 'Africa/Kinshasa');
        $soldAt = ($sale->completed_at ?? $sale->created_at)?->timezone($timezone);

        $paid = '0.00';
        foreach ($sale->payments as $payment) {
            $paid = bcadd($paid, (string) $payment->amount, 2);
        }

        $changeRaw = bcsub($paid, (string) $sale->grand_total, 2);
        $discountTotal = (string) $sale->discount_total;

        $itemCountRaw = '0';
        foreach ($sale->lines as $line) {
            $itemCountRaw = bcadd($itemCountRaw, (string) $line->quantity, 3);
        }
        $itemCount = (int) round((float) $itemCountRaw);

        $siteName = $sale->site?->name;
        $pharmacyName = (string) ($sale->tenant?->name ?: config('app.name', 'Manolya Pharma'));

        return new ThermalReceipt(
            saleId: (string) $sale->id,
            saleNumber: (string) $sale->number,
            brandName: 'MANOLYA PHARMA',
            pharmacyName: $pharmacyName,
            siteName: $siteName && strcasecmp($siteName, $pharmacyName) !== 0 ? $siteName : null,
            address: $this->nullableString($sale->site?->address),
            soldAtDate: $soldAt?->format('d/m/Y') ?? '',
            soldAtTime: $soldAt?->format('H:i') ?? '',
            cashierName: (string) ($sale->cashier?->name ?: 'Caisse'),
            paymentLabel: $this->combinedPaymentLabel($sale),
            registerNumber: $this->nullableString($sale->cashRegisterSession?->number),
            lines: $sale->lines
                ->map(fn (SaleLine $line) => $this->mapLine($line, $symbol))
                ->values()
                ->all(),
            subtotal: $this->money->format((string) $sale->subtotal, $symbol),
            discount: bccomp($discountTotal, '0', 2) === 1
                ? $this->money->format($discountTotal, $symbol)
                : null,
            grandTotal: $this->money->format((string) $sale->grand_total, $symbol),
            amountPaid: $this->money->format($paid, $symbol),
            change: bccomp($changeRaw, '0', 2) === 1
                ? $this->money->format($changeRaw, $symbol)
                : null,
            itemCountLabel: $itemCount <= 1
                ? $itemCount.' article'
                : $itemCount.' articles',
            footerMessage: 'Votre santé, notre priorité.',
            currencySymbol: $symbol,
            isReprint: $isReprint,
            statusLabel: $sale->status === Sale::STATUS_VOIDED ? 'ANNULÉE' : null,
        );
    }

    /**
     * @return array{name: string, quantity_label: string, unit_price: string, line_total: string, lot: string|null}
     */
    private function mapLine(SaleLine $line, string $symbol): array
    {
        $quantity = $this->money->formatQuantity((string) $line->quantity);
        $unitPrice = $this->money->format((string) $line->unit_price, $symbol);

        return [
            'name' => (string) ($line->product?->commercial_name ?: 'Produit'),
            'quantity_label' => $quantity.' x '.$unitPrice,
            'unit_price' => $unitPrice,
            'line_total' => $this->money->format((string) $line->line_total, $symbol),
            'lot' => $this->nullableString($line->batch?->lot_number),
        ];
    }

    private function combinedPaymentLabel(Sale $sale): string
    {
        $labels = $sale->payments
            ->map(fn (SalePayment $payment) => $this->paymentMethodLabel($payment))
            ->unique()
            ->values();

        return $labels->isEmpty() ? '—' : $labels->implode(' + ');
    }

    private function paymentMethodLabel(SalePayment $payment): string
    {
        $base = match ($payment->method) {
            'cash' => 'Espèces',
            'card' => 'Carte',
            'mobile_money' => 'Mobile Money',
            default => (string) $payment->method,
        };

        $provider = match ($payment->provider) {
            'orange', 'orange_money' => 'Orange Money',
            'airtel', 'airtel_money' => 'Airtel Money',
            'mtn', 'mtn_momo' => 'MTN MoMo',
            default => null,
        };

        return $provider ? $base.' · '.$provider : $base;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
