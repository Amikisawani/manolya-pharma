<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_orders.view');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.view')
            && ($user->isSuperAdmin() || $user->tenant_id === $purchaseOrder->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('purchase_orders.create');
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.submit')
            && ($user->isSuperAdmin() || $user->tenant_id === $purchaseOrder->tenant_id);
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.approve')
            && ($user->isSuperAdmin() || $user->tenant_id === $purchaseOrder->tenant_id);
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.receive')
            && ($user->isSuperAdmin() || $user->tenant_id === $purchaseOrder->tenant_id);
    }
}
