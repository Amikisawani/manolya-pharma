<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionRegistry extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'sessions_registry';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'ip',
        'user_agent',
        'last_activity_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
