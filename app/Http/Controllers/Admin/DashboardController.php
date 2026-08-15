<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $tenant = Tenant::query()->orderBy('created_at')->first();

        if ($tenant) {
            app()->instance('current_tenant_id', (string) $tenant->id);
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'users' => User::query()->whereNotNull('tenant_id')->count(),
                'products' => $tenant ? Product::query()->count() : 0,
                'sales' => $tenant ? Sale::query()->count() : 0,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ] : null,
            'admin' => [
                'name' => auth()->user()?->name,
                'email' => auth()->user()?->email,
            ],
        ]);
    }
}
