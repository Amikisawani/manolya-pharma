<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'tenant_id',
        'sale_id',
        'cash_register_session_id',
        'number',
        'status',
        'restock',
        'reason',
        'refund_method',
        'refund_total',
        'processed_by',
        'processed_at',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'restock' => 'boolean',
            'refund_total' => 'decimal:2',
            'processed_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleReturnLine::class);
    }
}
