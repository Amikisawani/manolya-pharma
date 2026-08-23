<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login', [
            'activeSession' => Auth::check() ? [
                'name' => Auth::user()?->name,
                'email' => Auth::user()?->email,
                'context' => Auth::user()?->isSuperAdmin() ? 'admin' : 'pharmacie',
            ] : null,
        ]);
    }

    public function store(LoginRequest $request, LoginAttemptService $attempts): RedirectResponse
    {
        if (Auth::check()) {
            Auth::logout();
        }

        $user = $attempts->attempt($request, LoginAttemptService::CONTEXT_ADMIN);

        if ($user->hasTwoFactorEnabled()) {
            return $attempts->beginTwoFactorChallenge(
                $request,
                $user,
                false,
                'admin.dashboard',
                LoginAttemptService::CONTEXT_ADMIN,
            );
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request, CashRegisterSessionService $sessions): RedirectResponse
    {
        if ($message = $sessions->logoutBlockMessage($request->user())) {
            return redirect()->route('admin.dashboard')->with('error', $message);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
