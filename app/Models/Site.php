<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'legal_rccm',
        'legal_id_nat',
        'legal_nif',
        'logo_path',
        'receipt_footer',
        'receipt_return_policy',
        'receipt_auto_print',
        'receipt_show_qr',
        'is_main',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'receipt_auto_print' => 'boolean',
            'receipt_show_qr' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
