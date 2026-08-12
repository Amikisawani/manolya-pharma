<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view') || $user->can('sales.pos');
    }

    public function view(User $user, Sale $sale): bool
    {
        return ($user->can('sales.view') || $user->can('sales.pos'))
            && ($user->isSuperAdmin() || $user->tenant_id === $sale->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('sales.pos');
    }

    public function refund(User $user, Sale $sale): bool
    {
        return $user->can('sales.refund')
            && ($user->isSuperAdmin() || $user->tenant_id === $sale->tenant_id);
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->can('sales.void')
            && ($user->isSuperAdmin() || $user->tenant_id === $sale->tenant_id);
    }
}
