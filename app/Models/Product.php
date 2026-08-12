<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'sku',
        'commercial_name',
        'generic_name',
        'barcode',
        'qr_payload',
        'manufacturer',
        'preferred_supplier_id',
        'purchase_price',
        'sale_price',
        'currency_code',
        'min_stock',
        'critical_stock',
        'allocation_strategy',
        'description',
        'image_path',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'critical_stock' => 'decimal:3',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'sku' => $this->sku,
            'commercial_name' => $this->commercial_name,
            'generic_name' => $this->generic_name,
            'barcode' => $this->barcode,
            'manufacturer' => $this->manufacturer,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
