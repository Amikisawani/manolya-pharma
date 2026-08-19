<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ManolyaBootstrap;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyOwnerBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_pharmacy_owner_for_login_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = app(ManolyaBootstrap::class)->bootstrapPlatform([
            'name' => 'Ami Kisawani',
            'email' => 'amikisawani71@gmail.com',
            'password' => 'amikis150898',
        ]);

        $this->assertTrue($admin->isSuperAdmin());

        $owner = User::query()->where('email', 'owner@manolya-pharma.site')->first();
        $this->assertNotNull($owner);
        $this->assertNotNull($owner->tenant_id);
        $this->assertTrue($owner->hasRole('owner'));

        $this->post('/login', [
            'email' => 'owner@manolya-pharma.site',
            'password' => 'amikis150898',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_ensure_pharmacy_owner_is_noop_when_accounts_exist(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $bootstrap = app(ManolyaBootstrap::class);
        $bootstrap->bootstrapPlatform([
            'name' => 'Ami Kisawani',
            'email' => 'amikisawani71@gmail.com',
            'password' => 'amikis150898',
        ]);

        $this->assertNull($bootstrap->ensurePharmacyOwner());
        $this->assertSame(1, User::query()->whereNotNull('tenant_id')->count());
    }

    public function test_bootstrap_command_restores_missing_pharmacy_owner(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(ManolyaBootstrap::class)->bootstrapPlatform([
            'name' => 'Ami Kisawani',
            'email' => 'amikisawani71@gmail.com',
            'password' => 'amikis150898',
        ]);

        User::query()->whereNotNull('tenant_id')->forceDelete();
        $this->assertSame(0, User::query()->whereNotNull('tenant_id')->count());

        $this->artisan('manolya:bootstrap')
            ->expectsOutputToContain('Compte pharmacie recréé')
            ->assertSuccessful();

        $this->assertTrue(
            User::query()->where('email', 'owner@manolya-pharma.site')->exists()
        );
    }

    public function test_super_admin_cannot_use_pharmacy_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        app(ManolyaBootstrap::class)->bootstrapPlatform([
            'name' => 'Ami Kisawani',
            'email' => 'amikisawani71@gmail.com',
            'password' => 'amikis150898',
        ]);

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'amikisawani71@gmail.com',
                'password' => 'amikis150898',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }
}
