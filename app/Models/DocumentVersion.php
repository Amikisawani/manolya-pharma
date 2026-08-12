<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'document_id',
        'version',
        'disk_path',
        'mime',
        'size',
        'ocr_status',
        'ocr_text',
        'ocr_engine',
        'ocr_error',
        'ocr_processed_at',
        'uploaded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'size' => 'integer',
            'ocr_processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if ($version->created_at === null) {
                $version->created_at = now();
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
