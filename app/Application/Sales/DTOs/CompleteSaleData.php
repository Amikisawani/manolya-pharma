<?php

namespace App\Application\Sales\DTOs;

final readonly class CompleteSaleData
{
    /**
     * @param  list<array{
     *     product_id: string,
     *     quantity: string,
     *     unit_price: string,
     *     discount_amount?: string
     * }>  $lines
     * @param  list<array{
     *     method: string,
     *     amount: string,
     *     provider?: string|null,
     *     provider_ref?: string|null
     * }>  $payments
     */
    public function __construct(
        public string $tenantId,
        public string $siteId,
        public string $warehouseId,
        public string $cashierId,
        public string $currencyCode,
        public array $lines,
        public array $payments,
        public string $discountTotal = '0.00',
        public ?string $number = null,
        public ?string $cashRegisterSessionId = null,
        public ?string $customerName = null,
        public ?string $amountTendered = null,
        public ?string $note = null,
    ) {}
}
