<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request, Google2FA $google2fa): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('login.id');
        if (! $userId) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $valid = $google2fa->verifyKey((string) $user->two_factor_secret, $request->string('code')->toString());

        if (! $valid) {
            $recovery = collect($user->two_factor_recovery_codes ?? []);
            $code = $request->string('code')->toString();
            if (! $recovery->contains($code)) {
                throw ValidationException::withMessages([
                    'code' => 'Code 2FA invalide.',
                ]);
            }

            $user->forceFill([
                'two_factor_recovery_codes' => $recovery->reject(fn ($c) => $c === $code)->values()->all(),
            ])->save();
        }

        Auth::login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget('login.id');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function setup(Request $request, Google2FA $google2fa): Response
    {
        /** @var User $user */
        $user = $request->user();

        $secret = $user->two_factor_secret ?: $google2fa->generateSecretKey();

        if (! $user->two_factor_secret) {
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => collect(range(1, 8))->map(fn () => strtoupper(bin2hex(random_bytes(4))))->all(),
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
        $request->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        if (! $google2fa->verifyKey((string) $user->two_factor_secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Code de confirmation invalide.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return redirect()->route('profile.edit')->with('success', 'Authentification à deux facteurs activée.');
    }
}
