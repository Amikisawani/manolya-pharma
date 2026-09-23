<?php

namespace App\Services;

use App\Domain\Shared\Formatting\MoneyFormatter;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class StorefrontCatalog
{
    /**
     * @var array<string, string>
     */
    private const FOLD_MAP = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a',
        'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o',
        'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'Ç' => 'c',
    ];

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

    public function applyProductSearch(Builder $builder, string $query): void
    {
        $needle = '%'.$this->escapeLike(strtr(mb_strtolower(trim($query)), self::FOLD_MAP)).'%';

        if ($needle === '%%') {
            return;
        }

        $name = $this->foldedSql('commercial_name');
        $generic = $this->foldedSql("coalesce(generic_name, '')");
        $sku = $this->foldedSql('sku');

        $builder->where(function (Builder $inner) use ($needle, $name, $generic, $sku): void {
            $inner->whereRaw($name.' like ?', [$needle])
                ->orWhereRaw($generic.' like ?', [$needle])
                ->orWhereRaw($sku.' like ?', [$needle]);
        });
    }

    private function foldedSql(string $expression): string
    {
        $sql = 'lower('.$expression.')';

        foreach (['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', 'ô' => 'o', 'î' => 'i', 'É' => 'e', 'È' => 'e', 'À' => 'a', 'Ç' => 'c'] as $from => $to) {
            $sql = "replace({$sql}, '{$from}', '{$to}')";
        }

        return $sql;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], '', $value);
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
            'country' => (string) ($storefront['country'] ?? 'République démocratique du Congo'),
            'phone' => (string) ($storefront['phone'] ?? ''),
            'email' => (string) ($storefront['email'] ?? ''),
            'address' => (string) ($storefront['address'] ?? 'Kinshasa'),
            'hours' => (string) ($storefront['hours'] ?? 'Lundi — Samedi, 8h — 19h'),
        ];
    }
}
