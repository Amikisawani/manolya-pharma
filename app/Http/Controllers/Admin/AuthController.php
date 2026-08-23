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
        $user = Auth::guard('admin')->user();

        return Inertia::render('Admin/Auth/Login', [
            'activeSession' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'context' => 'admin',
            ] : null,
        ]);
    }

    public function store(LoginRequest $request, LoginAttemptService $attempts): RedirectResponse
    {
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

        Auth::guard('admin')->logout();
        Auth::guard('admin')->login($user, false);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request, CashRegisterSessionService $sessions): RedirectResponse
    {
        if ($message = $sessions->logoutBlockMessage($request->user('admin'))) {
            return redirect()->route('admin.dashboard')->with('error', $message);
        }

        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
