<?php

namespace App\Models;

use App\Casts\TrimmedDecimal;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLine extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'product_id',
        'batch_id',
        'quantity',
        'quantity_returned',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => TrimmedDecimal::class.':3',
            'quantity_returned' => TrimmedDecimal::class.':3',
            'unit_price' => TrimmedDecimal::class.':2',
            'unit_cost' => TrimmedDecimal::class.':2',
            'discount_amount' => TrimmedDecimal::class.':2',
            'line_total' => TrimmedDecimal::class.':2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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
