<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'type',
        'title',
        'current_version',
        'search_text',
        'deleted_by',
        'delete_reason',
    ];

    protected function casts(): array
    {
        return [
            'current_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Full-text search (PostgreSQL) with ILIKE fallback for partial tokens.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($term, $like): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $inner->whereRaw(
                    "to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(type, '') || ' ' || coalesce(search_text, ''))
                     @@ plainto_tsquery('simple', ?)",
                    [$term]
                )->orWhere('title', 'ilike', $like)
                    ->orWhere('type', 'ilike', $like)
                    ->orWhere('search_text', 'ilike', $like);
            } else {
                $inner->where('title', 'like', $like)
                    ->orWhere('type', 'like', $like)
                    ->orWhere('search_text', 'like', $like);
            }
        });
    }
}
