<?php

namespace App\Domain\Sales\Receipts;

use App\Domain\Shared\Formatting\MoneyFormatter;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Storage;

final class ThermalReceiptBuilder
{
    public function __construct(
        private readonly MoneyFormatter $money,
        private readonly ReceiptQrCodeGenerator $qrCodes,
    ) {}

    public function fromSale(Sale $sale, bool $isReprint = false): ThermalReceipt
    {
        $sale->loadMissing([
            'lines.product:id,commercial_name,generic_name,sku',
            'lines.batch:id,lot_number',
            'payments',
            'cashier:id,name',
            'site',
            'tenant:id,name,timezone,default_currency',
            'cashRegisterSession:id,number',
        ]);

        $site = $sale->site;
        $tenant = $sale->tenant;
        $symbol = config('currency.symbol', 'Fc');
        $timezone = $tenant?->timezone ?: config('app.timezone', 'Africa/Kinshasa');
        $soldAt = ($sale->completed_at ?? $sale->created_at)?->timezone($timezone);

        $lines = $sale->lines
            ->map(fn (SaleLine $line) => $this->mapLine($line, $symbol))
            ->values()
            ->all();

        $payments = $sale->payments
            ->map(fn (SalePayment $payment) => [
                'label' => $this->paymentMethodLabel($payment),
                'amount' => $this->money->format((string) $payment->amount, $symbol),
            ])
            ->values()
            ->all();

        $itemCountRaw = '0';
        foreach ($sale->lines as $line) {
            $itemCountRaw = bcadd($itemCountRaw, (string) $line->quantity, 3);
        }
        $itemCount = (int) round((float) $itemCountRaw);

        $discountTotal = (string) $sale->discount_total;
        $changeGiven = (string) ($sale->change_given ?? '0.00');
        $amountTendered = (string) ($sale->amount_tendered ?? $sale->grand_total);
        $showQr = (bool) ($site?->receipt_show_qr ?? true);
        $saleNumber = (string) $sale->number;

        $siteName = $site?->name;
        $pharmacyName = (string) ($tenant?->name ?: config('app.name', 'Manolya Pharma'));

        return new ThermalReceipt(
            saleId: (string) $sale->id,
            saleNumber: $saleNumber,
            brandName: 'MANOLYA PHARMA',
            pharmacyName: $pharmacyName,
            siteName: $siteName && strcasecmp($siteName, $pharmacyName) !== 0 ? $siteName : null,
            address: $this->nullableString($site?->address),
            phone: $this->nullableString($site?->phone),
            email: $this->nullableString($site?->email),
            legalLines: $this->legalLines($site),
            logoSrc: $this->logoSrc($site?->logo_path),
            soldAtDate: $soldAt?->format('d/m/Y') ?? '',
            soldAtTime: $soldAt?->format('H:i') ?? '',
            cashierName: (string) ($sale->cashier?->name ?: 'Caisse'),
            customerName: $this->nullableString($sale->customer_name) ?? 'Client comptoir',
            paymentLabel: $this->combinedPaymentLabel($sale),
            registerNumber: $this->nullableString($sale->cashRegisterSession?->number),
            transactionRef: $this->transactionRef($sale),
            lines: $lines,
            subtotal: $this->money->format((string) $sale->subtotal, $symbol),
            discount: bccomp($discountTotal, '0', 2) === 1
                ? $this->money->format($discountTotal, $symbol)
                : null,
            tax: null,
            grandTotal: $this->money->format((string) $sale->grand_total, $symbol),
            amountPaid: $this->money->format($amountTendered, $symbol),
            change: bccomp($changeGiven, '0', 2) === 1
                ? $this->money->format($changeGiven, $symbol)
                : null,
            itemCount: $itemCount,
            itemCountLabel: $itemCount <= 1
                ? $itemCount.' article'
                : $itemCount.' articles',
            note: $this->nullableString($sale->note),
            returnPolicy: $this->nullableString($site?->receipt_return_policy),
            footerMessage: $this->nullableString($site?->receipt_footer)
                ?? 'Votre santé, notre priorité.',
            qrSvg: $showQr ? $this->qrCodes->svg($this->qrPayload($sale)) : null,
            showQr: $showQr,
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

    /**
     * @return list<array{label: string, value: string}>
     */
    private function legalLines(mixed $site): array
    {
        $lines = [];
        foreach ([
            'RCCM' => $site?->legal_rccm ?? null,
            'ID Nat' => $site?->legal_id_nat ?? null,
            'NIF' => $site?->legal_nif ?? null,
        ] as $label => $value) {
            $value = $this->nullableString(is_string($value) ? $value : null);
            if ($value !== null) {
                $lines[] = ['label' => $label, 'value' => $value];
            }
        }

        return $lines;
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

    private function transactionRef(Sale $sale): ?string
    {
        foreach ($sale->payments as $payment) {
            $ref = $this->nullableString($payment->provider_ref);
            if ($ref !== null) {
                return $ref;
            }
        }

        return null;
    }

    private function qrPayload(Sale $sale): string
    {
        return implode('|', array_filter([
            'MANOLYA',
            (string) $sale->number,
            ($sale->completed_at ?? $sale->created_at)?->format('Y-m-d'),
        ]));
    }

    private function logoSrc(?string $path): ?string
    {
        $path = $this->nullableString($path);
        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'data:') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';
        $contents = $disk->get($path);
        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
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
