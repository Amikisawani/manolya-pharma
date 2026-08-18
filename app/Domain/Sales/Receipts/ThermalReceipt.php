<?php

namespace App\Domain\Sales\Receipts;

final readonly class ThermalReceipt
{
    /**
     * @param  list<array{name: string, quantity_label: string, unit_price: string, line_total: string, lot: string|null}>  $lines
     */
    public function __construct(
        public string $saleId,
        public string $saleNumber,
        public string $brandName,
        public string $pharmacyName,
        public ?string $siteName,
        public ?string $address,
        public string $soldAtDate,
        public string $soldAtTime,
        public string $cashierName,
        public string $paymentLabel,
        public ?string $registerNumber,
        public array $lines,
        public string $subtotal,
        public ?string $discount,
        public string $grandTotal,
        public string $amountPaid,
        public ?string $change,
        public string $itemCountLabel,
        public string $footerMessage,
        public string $currencySymbol,
        public bool $isReprint,
        public ?string $statusLabel,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sale_id' => $this->saleId,
            'sale_number' => $this->saleNumber,
            'brand_name' => $this->brandName,
            'pharmacy_name' => $this->pharmacyName,
            'site_name' => $this->siteName,
            'address' => $this->address,
            'sold_at_date' => $this->soldAtDate,
            'sold_at_time' => $this->soldAtTime,
            'cashier_name' => $this->cashierName,
            'payment_label' => $this->paymentLabel,
            'register_number' => $this->registerNumber,
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'grand_total' => $this->grandTotal,
            'amount_paid' => $this->amountPaid,
            'change' => $this->change,
            'item_count_label' => $this->itemCountLabel,
            'footer_message' => $this->footerMessage,
            'currency_symbol' => $this->currencySymbol,
            'is_reprint' => $this->isReprint,
            'status_label' => $this->statusLabel,
        ];
    }
}
