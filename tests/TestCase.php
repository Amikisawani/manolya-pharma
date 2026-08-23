<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function makeTenant(): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Pharmacie Test',
            'slug' => 'pharmacie-test-'.Str::uuid(),
            'status' => 'active',
        ]);
    }

    protected function pharmacyUser(array $attributes = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create(array_merge([
            'tenant_id' => $this->makeTenant()->id,
            'is_active' => true,
        ], $attributes));

        $user->assignRole('owner');

        return $user;
    }

    protected function superAdmin(array $attributes = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create(array_merge([
            'tenant_id' => null,
            'is_active' => true,
        ], $attributes));

        $user->assignRole('super_admin');

        return $user;
    }
}
