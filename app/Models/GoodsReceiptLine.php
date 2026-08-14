<?php

namespace App\Models;

use App\Casts\TrimmedDecimal;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'goods_receipt_id',
        'product_id',
        'batch_id',
        'lot_number',
        'expires_at',
        'quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'quantity' => TrimmedDecimal::class.':3',
            'unit_cost' => TrimmedDecimal::class.':2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
