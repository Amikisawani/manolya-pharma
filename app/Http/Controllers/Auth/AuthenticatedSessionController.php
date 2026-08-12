<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $email = $request->string('email')->toString();

        /** @var User|null $candidate */
        $candidate = User::query()->where('email', $email)->first();

        if ($candidate?->locked_until !== null && $candidate->locked_until->isFuture()) {
            LoginHistory::query()->create([
                'user_id' => $candidate->id,
                'tenant_id' => $candidate->tenant_id,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'success' => false,
                'failure_reason' => 'locked',
            ]);

            throw ValidationException::withMessages([
                'email' => 'Compte temporairement verrouillé. Réessayez plus tard.',
            ]);
        }

        if ($candidate && ! $candidate->is_active) {
            LoginHistory::query()->create([
                'user_id' => $candidate->id,
                'tenant_id' => $candidate->tenant_id,
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'success' => false,
                'failure_reason' => 'inactive',
            ]);

            throw ValidationException::withMessages([
                'email' => 'Compte désactivé.',
            ]);
        }

        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            if ($candidate) {
                $attempts = (int) $candidate->failed_login_attempts + 1;
                $candidate->failed_login_attempts = $attempts;
                if ($attempts >= 5) {
                    $candidate->locked_until = now()->addMinutes(15);
                }
                $candidate->save();

                LoginHistory::query()->create([
                    'user_id' => $candidate->id,
                    'tenant_id' => $candidate->tenant_id,
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'success' => false,
                    'failure_reason' => 'invalid_credentials',
                ]);
            }

            throw $e;
        }

        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        LoginHistory::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'success' => true,
            'failure_reason' => null,
        ]);

        if ($user->hasTwoFactorEnabled()) {
            Auth::logout();

            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
