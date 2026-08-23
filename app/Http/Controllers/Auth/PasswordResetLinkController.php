<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * Always returns the same status so the form cannot be used to enumerate accounts.
     */
    public function store(Request $request, LoginAttemptService $attempts): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));

        /** @var User|null $user */
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user !== null) {
            Password::sendResetLink(['email' => $user->email]);
        } else {
            $attempts->dummyPasswordCheck();
        }

        return back()->with(
            'status',
            'Si un compte existe pour cette adresse, un lien de réinitialisation a été envoyé.',
        );
    }
}
