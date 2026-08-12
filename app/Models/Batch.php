<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_QUARANTINE = 'quarantine';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DEPLETED = 'depleted';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'lot_number',
        'manufactured_at',
        'expires_at',
        'quantity_on_hand',
        'unit_cost',
        'currency_code',
        'status',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'quantity_on_hand' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
