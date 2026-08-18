<?php

namespace App\Domain\Sales\Receipts;

final readonly class ThermalReceipt
{
    /**
     * @param  list<array{label: string, value: string}>  $legalLines
     * @param  list<array{name: string, quantity_label: string, unit_price: string, line_total: string, lot: string|null}>  $lines
     * @param  list<array{label: string, amount: string}>  $payments
     */
    public function __construct(
        public string $saleId,
        public string $saleNumber,
        public string $brandName,
        public string $pharmacyName,
        public ?string $siteName,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public array $legalLines,
        public ?string $logoSrc,
        public string $soldAtDate,
        public string $soldAtTime,
        public string $cashierName,
        public string $customerName,
        public string $paymentLabel,
        public ?string $registerNumber,
        public ?string $transactionRef,
        public array $lines,
        public string $subtotal,
        public ?string $discount,
        public ?string $tax,
        public string $grandTotal,
        public string $amountPaid,
        public ?string $change,
        public int $itemCount,
        public string $itemCountLabel,
        public ?string $note,
        public ?string $returnPolicy,
        public string $footerMessage,
        public ?string $qrSvg,
        public bool $showQr,
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
            'phone' => $this->phone,
            'email' => $this->email,
            'legal_lines' => $this->legalLines,
            'logo_src' => $this->logoSrc,
            'sold_at_date' => $this->soldAtDate,
            'sold_at_time' => $this->soldAtTime,
            'cashier_name' => $this->cashierName,
            'customer_name' => $this->customerName,
            'payment_label' => $this->paymentLabel,
            'register_number' => $this->registerNumber,
            'transaction_ref' => $this->transactionRef,
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'tax' => $this->tax,
            'grand_total' => $this->grandTotal,
            'amount_paid' => $this->amountPaid,
            'change' => $this->change,
            'item_count' => $this->itemCount,
            'item_count_label' => $this->itemCountLabel,
            'note' => $this->note,
            'return_policy' => $this->returnPolicy,
            'footer_message' => $this->footerMessage,
            'qr_svg' => $this->qrSvg,
            'show_qr' => $this->showQr,
            'currency_symbol' => $this->currencySymbol,
            'is_reprint' => $this->isReprint,
            'status_label' => $this->statusLabel,
        ];
    }
}
