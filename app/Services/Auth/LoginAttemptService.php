<?php

namespace App\Services\Auth;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginAttemptService
{
    public const FAILURE_MESSAGE = 'Identifiants incorrects. Vérifiez votre e-mail, mot de passe, et que vous utilisez la bonne page de connexion.';

    public const THROTTLE_MESSAGE = 'Trop de tentatives. Réessayez dans :seconds secondes.';

    public const CONTEXT_PHARMACY = 'pharmacy';

    public const CONTEXT_ADMIN = 'admin';

    private const MAX_EMAIL_ATTEMPTS = 5;

    private const MAX_IP_ATTEMPTS = 20;

    private const DECAY_SECONDS = 900;

    private const LOCK_AFTER = 5;

    private const LOCK_MINUTES = 15;

    /**
     * Known bcrypt hash used only to keep missing/locked account timing closer to a real password check.
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function attempt(Request $request, string $context): User
    {
        $this->ensureIsNotRateLimited($request, $context);

        $email = $this->normalizedEmail($request);
        $password = (string) $request->input('password');

        /** @var User|null $candidate */
        $candidate = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($candidate === null) {
            $this->dummyPasswordCheck();
            $this->fail($request, $context, null, incrementLock: false);
        }

        if ($candidate->locked_until !== null && $candidate->locked_until->isFuture()) {
            $this->dummyPasswordCheck();
            $this->record($candidate, $request, false, 'locked');
            $this->fail($request, $context, $candidate, incrementLock: false);
        }

        if (! $candidate->is_active) {
            $this->dummyPasswordCheck();
            $this->record($candidate, $request, false, 'inactive');
            $this->fail($request, $context, $candidate, incrementLock: false);
        }

        if (! Hash::check($password, $candidate->password)) {
            $this->record($candidate, $request, false, 'invalid_credentials');
            $this->fail($request, $context, $candidate, incrementLock: true);
        }

        if (! $this->matchesPortal($candidate, $context)) {
            $this->record($candidate, $request, false, 'wrong_portal');
            $this->fail($request, $context, $candidate, incrementLock: false);
        }

        $this->clearLimits($request, $context);

        $candidate->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->record($candidate, $request, true, null);

        return $candidate;
    }

    public function beginTwoFactorChallenge(
        Request $request,
        User $user,
        bool $remember,
        string $intendedRoute,
        string $context,
    ): RedirectResponse {
        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->regenerate();
        $request->session()->put('login.id', $user->id);
        $request->session()->put('login.remember', $context === self::CONTEXT_ADMIN ? false : $remember);
        $request->session()->put('login.intended', route($intendedRoute));
        $request->session()->put('login.context', $context);

        return redirect()->route('two-factor.challenge');
    }

    public function normalizedEmail(Request $request): string
    {
        return Str::lower(trim((string) $request->input('email')));
    }

    public function dummyPasswordCheck(): void
    {
        Hash::check('not-the-password', self::DUMMY_HASH);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(Request $request, string $context): void
    {
        $emailKey = $this->emailKey($request, $context);
        $ipKey = $this->ipKey($request, $context);

        if (
            ! RateLimiter::tooManyAttempts($emailKey, self::MAX_EMAIL_ATTEMPTS)
            && ! RateLimiter::tooManyAttempts($ipKey, self::MAX_IP_ATTEMPTS)
        ) {
            return;
        }

        event(new Lockout($request));

        $seconds = max(
            RateLimiter::availableIn($emailKey),
            RateLimiter::availableIn($ipKey),
        );

        throw ValidationException::withMessages([
            'email' => [str_replace(':seconds', (string) $seconds, self::THROTTLE_MESSAGE)],
        ]);
    }

    private function fail(Request $request, string $context, ?User $candidate, bool $incrementLock): never
    {
        RateLimiter::hit($this->emailKey($request, $context), self::DECAY_SECONDS);
        RateLimiter::hit($this->ipKey($request, $context), self::DECAY_SECONDS);

        if ($incrementLock && $candidate !== null) {
            $attempts = (int) $candidate->failed_login_attempts + 1;
            $candidate->failed_login_attempts = $attempts;

            if ($attempts >= self::LOCK_AFTER) {
                $candidate->locked_until = now()->addMinutes(self::LOCK_MINUTES);
            }

            $candidate->save();
        }

        throw ValidationException::withMessages([
            'email' => [self::FAILURE_MESSAGE],
        ]);
    }

    private function clearLimits(Request $request, string $context): void
    {
        RateLimiter::clear($this->emailKey($request, $context));
        RateLimiter::clear($this->ipKey($request, $context));
    }

    private function matchesPortal(User $user, string $context): bool
    {
        if ($context === self::CONTEXT_ADMIN) {
            return $user->isSuperAdmin();
        }

        return ! $user->isSuperAdmin() && filled($user->tenant_id);
    }

    private function record(User $user, Request $request, bool $success, ?string $reason): void
    {
        LoginHistory::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'success' => $success,
            'failure_reason' => $reason,
        ]);
    }

    private function emailKey(Request $request, string $context): string
    {
        return 'login-email:'.$context.':'.Str::transliterate($this->normalizedEmail($request)).'|'.$request->ip();
    }

    private function ipKey(Request $request, string $context): string
    {
        return 'login-ip:'.$context.':'.$request->ip();
    }
}
