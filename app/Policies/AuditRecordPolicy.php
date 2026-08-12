<?php

namespace App\Policies;

use App\Models\AuditRecord;
use App\Models\User;

class AuditRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, AuditRecord $auditRecord): bool
    {
        return $user->can('audit.view')
            && ($user->isSuperAdmin() || $user->tenant_id === $auditRecord->tenant_id);
    }

    public function export(User $user): bool
    {
        return $user->can('audit.export');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditRecord $auditRecord): bool
    {
        return false;
    }

    public function delete(User $user, AuditRecord $auditRecord): bool
    {
        return false;
    }
}
