<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $table = 'login_histories';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'ip',
        'user_agent',
        'success',
        'failure_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $history): void {
            if ($history->created_at === null) {
                $history->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
