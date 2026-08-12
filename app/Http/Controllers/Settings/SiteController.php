<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Infrastructure\Audit\AuditLogger;
use App\Models\Site;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeOwner($request);

        return Inertia::render('Settings/Sites/Index', [
            'sites' => Site::query()
                ->with('warehouses')
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'warehouse_name' => ['required', 'string', 'max:255'],
            'warehouse_code' => ['required', 'string', 'max:50'],
        ]);

        $site = Site::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'is_main' => false,
        ]);

        Warehouse::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'site_id' => $site->id,
            'name' => $data['warehouse_name'],
            'code' => $data['warehouse_code'],
            'is_default' => true,
        ]);

        $audit->log('site.created', $site, null, $site->toArray());

        return redirect()->route('settings.sites.index')->with('success', 'Site créé.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['owner', 'super_admin']),
            403
        );
    }
}
