<?php

namespace App\Application\Catalog\DTOs;

final readonly class ProductData
{
    public function __construct(
        public string $sku,
        public string $commercialName,
        public string $salePrice,
        public string $currencyCode,
        public ?string $categoryId = null,
        public ?string $genericName = null,
        public ?string $barcode = null,
        public ?string $qrPayload = null,
        public ?string $manufacturer = null,
        public ?string $preferredSupplierId = null,
        public string $purchasePrice = '0.00',
        public string $minStock = '0',
        public string $criticalStock = '0',
        public string $allocationStrategy = 'fefo',
        public ?string $description = null,
        public ?string $imagePath = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'commercial_name' => $this->commercialName,
            'generic_name' => $this->genericName,
            'category_id' => $this->categoryId,
            'barcode' => $this->barcode,
            'qr_payload' => $this->qrPayload,
            'manufacturer' => $this->manufacturer,
            'preferred_supplier_id' => $this->preferredSupplierId,
            'purchase_price' => $this->purchasePrice,
            'sale_price' => $this->salePrice,
            'currency_code' => $this->currencyCode,
            'min_stock' => $this->minStock,
            'critical_stock' => $this->criticalStock,
            'allocation_strategy' => $this->allocationStrategy,
            'description' => $this->description,
            'image_path' => $this->imagePath,
        ];
    }
}
