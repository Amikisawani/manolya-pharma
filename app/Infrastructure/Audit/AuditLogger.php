<?php

namespace App\Infrastructure\Audit;

use App\Models\AuditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
        ?string $tenantId = null,
    ): AuditRecord {
        $userId ??= auth()->id();
        $tenantId ??= $auditable?->getAttribute('tenant_id')
            ?? (app()->bound('current_tenant_id') ? app('current_tenant_id') : null)
            ?? auth()->user()?->tenant_id;

        return AuditRecord::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
