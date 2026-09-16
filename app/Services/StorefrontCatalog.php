<?php

namespace App\Services;

use App\Domain\Shared\Formatting\MoneyFormatter;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;

class StorefrontCatalog
{
    public function __construct(private readonly MoneyFormatter $money) {}

    public function bindPharmacyTenant(): ?Tenant
    {
        $tenant = Tenant::query()
            ->where('status', 'active')
            ->orderBy('created_at')
            ->first()
            ?? Tenant::query()->orderBy('created_at')->first();

        if ($tenant) {
            app()->instance('current_tenant_id', (string) $tenant->id);
        }

        return $tenant;
    }

    /**
     * @return array{id: string, name: string, generic_name: string|null, category: string|null, price: string}
     */
    public function presentProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->commercial_name,
            'generic_name' => $product->generic_name,
            'category' => $product->category?->name,
            'price' => $this->money->format((string) $product->sale_price),
        ];
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function categories(): array
    {
        return Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, tagline: string, city: string, country: string, phone: string, email: string, address: string, hours: string}
     */
    public function pharmacy(Tenant $tenant): array
    {
        $storefront = config('manolya.storefront', []);

        return [
            'name' => $tenant->name ?: (string) config('manolya.bootstrap.pharmacy_name'),
            'tagline' => (string) ($storefront['tagline'] ?? 'Officine de confiance à Kinshasa'),
            'city' => (string) ($storefront['city'] ?? 'Kinshasa'),
            'country' => (string) ($storefront['country'] ?? 'République du Congo'),
            'phone' => (string) ($storefront['phone'] ?? ''),
            'email' => (string) ($storefront['email'] ?? ''),
            'address' => (string) ($storefront['address'] ?? 'Kinshasa'),
            'hours' => (string) ($storefront['hours'] ?? 'Lundi — Samedi, 8h — 19h'),
        ];
    }
}
