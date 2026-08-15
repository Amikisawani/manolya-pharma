<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Services\ManolyaBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function create(ManolyaBootstrap $bootstrap): Response|RedirectResponse
    {
        if (! $bootstrap->needsSetup()) {
            return redirect()->route('login');
        }

        return Inertia::render('Setup/Create', [
            'defaults' => [
                'name' => config('manolya.bootstrap.owner_name'),
                'email' => config('manolya.bootstrap.owner_email'),
                'pharmacy_name' => config('manolya.bootstrap.pharmacy_name'),
                'site_name' => config('manolya.bootstrap.site_name'),
                'site_code' => config('manolya.bootstrap.site_code'),
            ],
        ]);
    }

    public function store(Request $request, ManolyaBootstrap $bootstrap): RedirectResponse
    {
        abort_unless($bootstrap->needsSetup(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'pharmacy_name' => ['required', 'string', 'max:255'],
            'site_name' => ['required', 'string', 'max:255'],
            'site_code' => ['required', 'string', 'max:50'],
        ]);

        $user = $bootstrap->createVirginPharmacy($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Pharmacie initialisée. Bienvenue !');
    }
}
