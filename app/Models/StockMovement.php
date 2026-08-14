<?php

namespace App\Models;

use App\Casts\TrimmedDecimal;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    public const UPDATED_AT = null;

    public const TYPE_IN_PURCHASE = 'IN_PURCHASE';

    public const TYPE_IN_RETURN = 'IN_RETURN';

    public const TYPE_IN_ADJUSTMENT = 'IN_ADJUSTMENT';

    public const TYPE_OUT_SALE = 'OUT_SALE';

    public const TYPE_OUT_RETURN_SUPPLIER = 'OUT_RETURN_SUPPLIER';

    public const TYPE_OUT_ADJUSTMENT = 'OUT_ADJUSTMENT';

    public const TYPE_OUT_EXPIRED = 'OUT_EXPIRED';

    public const TYPE_TRANSFER = 'TRANSFER';

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'product_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => TrimmedDecimal::class.':3',
            'unit_cost' => TrimmedDecimal::class.':2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            if ($movement->created_at === null) {
                $movement->created_at = now();
            }

            if ($movement->occurred_at === null) {
                $movement->occurred_at = $movement->created_at;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
