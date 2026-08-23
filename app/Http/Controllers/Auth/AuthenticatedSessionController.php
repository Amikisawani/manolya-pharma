<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginAttemptService;
use App\Services\ManolyaBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(ManolyaBootstrap $bootstrap): Response|RedirectResponse
    {
        if ($bootstrap->needsSetup()) {
            return redirect()->route('setup.create');
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'activeSession' => Auth::check() ? [
                'name' => Auth::user()?->name,
                'email' => Auth::user()?->email,
                'context' => Auth::user()?->isSuperAdmin() ? 'admin' : 'pharmacie',
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, LoginAttemptService $attempts): RedirectResponse
    {
        if (Auth::check()) {
            Auth::logout();
        }

        $user = $attempts->attempt($request, LoginAttemptService::CONTEXT_PHARMACY);

        if ($user->hasTwoFactorEnabled()) {
            return $attempts->beginTwoFactorChallenge(
                $request,
                $user,
                $request->boolean('remember'),
                'dashboard',
                LoginAttemptService::CONTEXT_PHARMACY,
            );
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, CashRegisterSessionService $sessions): RedirectResponse
    {
        if ($message = $sessions->logoutBlockMessage($request->user())) {
            return back()->with('error', $message);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
