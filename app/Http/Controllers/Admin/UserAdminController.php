<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Infrastructure\Audit\AuditLogger;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ManolyaBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    public function index(Request $request, ManolyaBootstrap $bootstrap): Response
    {
        $tenant = $bootstrap->ensureVirginPharmacyStructure();
        $site = Site::query()->where('tenant_id', $tenant->id)->orderByDesc('is_main')->first();

        return Inertia::render('Admin/Users/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'users' => User::query()
                ->where('tenant_id', $tenant->id)
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'is_active' => $u->is_active,
                    'role' => $u->roles->first()?->name,
                ]),
            'roles' => Role::query()
                ->whereIn('name', ['owner', 'pharmacist', 'stock_manager', 'cashier', 'accountant', 'auditor'])
                ->orderBy('name')
                ->pluck('name'),
            'default_site_id' => $site?->id,
        ]);
    }

    public function store(Request $request, AuditLogger $audit, ManolyaBootstrap $bootstrap): RedirectResponse
    {
        $tenant = $bootstrap->ensureVirginPharmacyStructure();
        $site = Site::query()->where('tenant_id', $tenant->id)->orderByDesc('is_main')->first();

        abort_unless($site, 422, 'Aucun site pharmacie : impossible de créer un compte.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['owner', 'pharmacist', 'stock_manager', 'cashier', 'accountant', 'auditor'])],
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$data['role']]);
        abort_if($user->fresh()->tenant_id === null, 500, 'Échec attribution tenant.');

        $audit->log('admin.user.created', $user, null, ['email' => $user->email, 'role' => $data['role']]);

        return back()->with('success', 'Compte pharmacie créé : '.$user->email);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403);
        abort_unless($user->tenant_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['owner', 'pharmacist', 'stock_manager', 'cashier', 'accountant', 'auditor'])],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', Password::defaults()],
        ]);

        $before = $user->only(['name', 'email', 'phone', 'is_active']);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        $audit->log('admin.user.updated', $user, $before, $user->only(['name', 'email', 'phone', 'is_active']));

        return back()->with('success', 'Compte mis à jour.');
    }

    public function destroy(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403);
        abort_unless($user->tenant_id, 404);

        $audit->log('admin.user.deactivated', $user, $user->toArray(), null);
        $user->update(['is_active' => false]);
        $user->delete();

        return back()->with('success', 'Compte désactivé.');
    }
}
