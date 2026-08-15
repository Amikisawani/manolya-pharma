<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes Eloquent queries to the current tenant and stamps tenant_id on create.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = static::resolveCurrentTenantId();

            if ($tenantId === null) {
                // Évite qu’un user sans tenant voie toutes les données (scope désactivé)
                if (auth()->check() && auth()->user()?->tenant_id === null && ! auth()->user()->isSuperAdmin()) {
                    $builder->whereRaw('0 = 1');
                }

                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.tenant_id',
                $tenantId
            );
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = static::resolveCurrentTenantId();

            if ($tenantId !== null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    protected static function resolveCurrentTenantId(): ?string
    {
        if (app()->bound('current_tenant_id')) {
            $bound = app('current_tenant_id');

            if (is_string($bound) && $bound !== '') {
                return $bound;
            }
        }

        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        $tenantId = $user->getAttribute('tenant_id');

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
