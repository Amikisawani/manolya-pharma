<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Infrastructure\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeOwner($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
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
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['owner', 'pharmacist', 'stock_manager', 'cashier', 'accountant', 'auditor'])],
        ]);

        $user = User::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'site_id' => $request->user()->site_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);
        $audit->log('user.created', $user, null, ['email' => $user->email, 'role' => $data['role']]);

        return back()->with('success', 'Compte créé : '.$user->email);
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->assertSameTenant($request, $user);

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

        $audit->log('user.updated', $user, $before, $user->only(['name', 'email', 'phone', 'is_active']));

        return back()->with('success', 'Compte mis à jour.');
    }

    public function destroy(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);
        $this->assertSameTenant($request, $user);

        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');

        $audit->log('user.deactivated', $user, $user->toArray(), null);
        $user->update(['is_active' => false]);
        $user->delete();

        return back()->with('success', 'Compte désactivé.');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['owner', 'super_admin']), 403);
    }

    private function assertSameTenant(Request $request, User $user): void
    {
        abort_unless(
            $request->user()?->tenant_id && $user->tenant_id === $request->user()->tenant_id,
            403
        );
    }
}
