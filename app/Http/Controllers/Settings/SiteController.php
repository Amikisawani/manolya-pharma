<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Infrastructure\Audit\AuditLogger;
use App\Models\Site;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $audit->log('site.created', $site, null, $site->only(['id', 'name', 'code']));

        return redirect()->route('settings.sites.index')->with('success', 'Site créé.');
    }

    public function update(Request $request, Site $site, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);
        abort_unless(
            $request->user()?->isSuperAdmin() || $request->user()?->tenant_id === $site->tenant_id,
            403
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'legal_rccm' => ['nullable', 'string', 'max:120'],
            'legal_id_nat' => ['nullable', 'string', 'max:120'],
            'legal_nif' => ['nullable', 'string', 'max:120'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'receipt_return_policy' => ['nullable', 'string', 'max:500'],
            'receipt_auto_print' => ['sometimes', 'boolean'],
            'receipt_show_qr' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('remove_logo') && $site->logo_path) {
            Storage::disk('public')->delete($site->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($site->logo_path) {
                Storage::disk('public')->delete($site->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('sites/logos', 'public');
        }

        unset($data['logo'], $data['remove_logo']);
        $data['receipt_auto_print'] = $request->boolean('receipt_auto_print');
        $data['receipt_show_qr'] = $request->has('receipt_show_qr')
            ? $request->boolean('receipt_show_qr')
            : (bool) $site->receipt_show_qr;

        $site->fill($data)->save();

        $audit->log('site.updated', $site, null, $site->only([
            'name', 'phone', 'email', 'receipt_auto_print', 'receipt_show_qr',
        ]));

        return redirect()->route('settings.sites.index')->with('success', 'Paramètres du site enregistrés.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['owner', 'super_admin']),
            403
        );
    }
}
