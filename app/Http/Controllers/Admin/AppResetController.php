<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Infrastructure\Audit\AuditLogger;
use App\Services\ManolyaBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AppResetController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->authorizeOwner($request);

        return Inertia::render('Admin/Reset', [
            'owner' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'pharmacy_name' => $request->user()->tenant?->name ?? config('manolya.bootstrap.pharmacy_name'),
        ]);
    }

    public function destroy(Request $request, ManolyaBootstrap $bootstrap, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeOwner($request);

        $data = $request->validate([
            'confirmation' => ['required', 'in:REINITIALISER'],
            'password' => ['required', 'current_password'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'new_password' => ['required', 'confirmed', Password::defaults()],
            'pharmacy_name' => ['required', 'string', 'max:255'],
        ]);

        $audit->log('app.factory_reset', $request->user(), null, [
            'email' => $data['email'],
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user = $bootstrap->factoryReset([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['new_password'],
            'pharmacy_name' => $data['pharmacy_name'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Application réinitialisée (vierge).');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['owner', 'super_admin']), 403);
    }
}
