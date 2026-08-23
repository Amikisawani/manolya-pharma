<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            $context = $request->session()->get('login.context');

            return redirect()->route(
                $context === LoginAttemptService::CONTEXT_ADMIN ? 'admin.login' : 'login'
            );
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:64'],
        ]);

        $userId = $request->session()->get('login.id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $throttleKey = 'two-factor:'.$userId.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => 'Trop de tentatives. Réessayez dans '.$seconds.' secondes.',
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);

        if ($user === null || ! $user->is_active) {
            $request->session()->forget(['login.id', 'login.remember', 'login.intended', 'login.context']);

            return redirect()->route('login');
        }

        $code = trim($request->string('code')->toString());
        $valid = $google2fa->verifyKey((string) $user->two_factor_secret, $code, 1);

        if (! $valid && ! $this->consumeRecoveryCode($user, $code)) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'code' => 'Code 2FA invalide.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $context = (string) $request->session()->pull('login.context', LoginAttemptService::CONTEXT_PHARMACY);
        $remember = $context === LoginAttemptService::CONTEXT_ADMIN
            ? false
            : (bool) $request->session()->pull('login.remember', false);

        $request->session()->forget(['login.id', 'login.remember', 'login.intended', 'login.context']);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        if ($context === LoginAttemptService::CONTEXT_ADMIN) {
            if (! $user->isSuperAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('admin.login');
            }

            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('dashboard');
    }

    public function setup(Request $request, Google2FA $google2fa): Response
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $user->two_factor_secret ?: $google2fa->generateSecretKey();

        if (! $user->two_factor_secret) {
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => collect(range(1, 8))
                    ->map(fn () => strtoupper(bin2hex(random_bytes(4))))
                    ->all(),
            ])->save();
        }

        $otpAuthUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Manolya Pharma'),
            $user->email,
            (string) $user->two_factor_secret
        );

        return Inertia::render('Auth/TwoFactorChallenge', [
            'setup' => true,
            'otpAuthUrl' => $otpAuthUrl,
            'secret' => $user->two_factor_secret,
            'recoveryCodes' => $user->two_factor_recovery_codes,
        ]);
    }

    public function enable(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'min:6', 'max:64']]);

        /** @var User $user */
        $user = $request->user();

        if (! $google2fa->verifyKey((string) $user->two_factor_secret, $request->string('code')->toString(), 1)) {
            throw ValidationException::withMessages([
                'code' => 'Code de confirmation invalide.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return redirect()->route('profile.edit')->with('success', 'Authentification à deux facteurs activée.');
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = strtoupper($code);
        $codes = $user->two_factor_recovery_codes ?? [];
        $matched = false;
        $remaining = [];

        foreach ($codes as $stored) {
            if (! $matched && hash_equals((string) $stored, $normalized)) {
                $matched = true;

                continue;
            }

            $remaining[] = $stored;
        }

        if (! $matched) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $remaining,
        ])->save();

        return true;
    }
}
