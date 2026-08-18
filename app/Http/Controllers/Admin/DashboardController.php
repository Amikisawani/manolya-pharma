<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reporting\Services\CashSessionReportQuery;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CashSessionReportQuery $sessionReports): Response
    {
        $tenant = Tenant::query()->orderBy('created_at')->first();

        if ($tenant) {
            app()->instance('current_tenant_id', (string) $tenant->id);
        }

        $pendingClosures = $tenant
            ? CashRegisterSession::query()
                ->with(['opener:id,name', 'site:id,name', 'tenant:id,timezone'])
                ->where('status', CashRegisterSession::STATUS_CLOSURE_REQUESTED)
                ->orderByDesc('closure_requested_at')
                ->limit(8)
                ->get()
                ->map(fn (CashRegisterSession $session) => $sessionReports->presentRow($session))
                ->values()
                ->all()
            : [];

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'users' => User::query()->whereNotNull('tenant_id')->count(),
                'products' => $tenant ? Product::query()->count() : 0,
                'sales' => $tenant ? Sale::query()->count() : 0,
                'pending_closures' => count($pendingClosures),
            ],
            'pendingClosures' => $pendingClosures,
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
