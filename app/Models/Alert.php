<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Alert extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'type',
        'severity',
        'title',
        'body',
        'reference_type',
        'reference_id',
        'status',
        'raised_at',
        'acked_by',
        'acked_at',
    ];

    protected function casts(): array
    {
        return [
            'raised_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $alert): void {
            if ($alert->raised_at === null) {
                $alert->raised_at = now();
            }

            if ($alert->status === null) {
                $alert->status = 'open';
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acked_by');
    }
}
