<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\LoginAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->superAdmin();

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $cacheControl = (string) $this->get('/login')->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->pharmacyUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_email_is_normalized_before_authentication(): void
    {
        $user = $this->pharmacyUser(['email' => 'owner@manolya.test']);

        $this->post('/login', [
            'email' => '  OWNER@MANOLYA.TEST  ',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->pharmacyUser();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'email' => LoginAttemptService::FAILURE_MESSAGE,
        ]);

        $this->assertGuest();
    }

    public function test_unknown_and_known_accounts_share_the_same_login_error(): void
    {
        $user = $this->pharmacyUser();

        $unknown = $this->from('/login')->post('/login', [
            'email' => 'inconnu@example.com',
            'password' => 'wrong-password',
        ]);

        $known = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $unknown->assertSessionHasErrors(['email' => LoginAttemptService::FAILURE_MESSAGE]);
        $known->assertSessionHasErrors(['email' => LoginAttemptService::FAILURE_MESSAGE]);
        $this->assertGuest();
    }

    public function test_super_admin_cannot_use_pharmacy_login(): void
    {
        $admin = $this->superAdmin();

        $this->from('/login')->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => LoginAttemptService::FAILURE_MESSAGE,
        ]);

        $this->assertGuest();
    }

    public function test_pharmacy_user_cannot_use_admin_login(): void
    {
        $user = $this->pharmacyUser();

        $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => LoginAttemptService::FAILURE_MESSAGE,
        ]);

        $this->assertGuest();
    }

    public function test_super_admin_can_authenticate_on_admin_login(): void
    {
        $admin = $this->superAdmin();

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_admin_login_ignores_remember_me(): void
    {
        $admin = $this->superAdmin();
        $token = $admin->remember_token;

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($token, $admin->fresh()->remember_token);
    }

    public function test_locked_account_returns_generic_error(): void
    {
        $user = $this->pharmacyUser([
            'locked_until' => now()->addMinutes(10),
            'failed_login_attempts' => 5,
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => LoginAttemptService::FAILURE_MESSAGE,
        ]);

        $this->assertGuest();
    }

    public function test_five_failed_attempts_lock_the_account(): void
    {
        $user = $this->pharmacyUser();

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $user->refresh();
        $this->assertSame(5, (int) $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = $this->pharmacyUser();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
