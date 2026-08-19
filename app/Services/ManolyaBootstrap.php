<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PharmacyCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManolyaBootstrap
{
    public function ensureRoles(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function needsSetup(): bool
    {
        if (! Schema::hasTable('users')) {
            return true;
        }

        return ! User::query()
            ->whereNull('tenant_id')
            ->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))
            ->exists();
    }

    /**
     * @param  array{name: string, email: string, password: string, pharmacy_name?: string, site_name?: string, site_code?: string}  $admin
     */
    public function bootstrapPlatform(array $admin): User
    {
        $this->ensureRoles();

        return DB::transaction(function () use ($admin) {
            $superAdmin = User::query()->create([
                'tenant_id' => null,
                'site_id' => null,
                'name' => $admin['name'],
                'email' => strtolower($admin['email']),
                'password' => $admin['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $superAdmin->assignRole('super_admin');

            $this->ensureVirginPharmacyStructure([
                'pharmacy_name' => $admin['pharmacy_name'] ?? config('manolya.bootstrap.pharmacy_name'),
                'site_name' => $admin['site_name'] ?? config('manolya.bootstrap.site_name'),
                'site_code' => $admin['site_code'] ?? config('manolya.bootstrap.site_code'),
            ]);

            $this->ensurePharmacyOwner();

            return $superAdmin->fresh(['roles']);
        });
    }

    /**
     * @param  array{pharmacy_name?: string, site_name?: string, site_code?: string}  $meta
     */
    public function ensureVirginPharmacyStructure(array $meta = []): Tenant
    {
        $tenant = Tenant::query()->first();

        if ($tenant) {
            app()->instance('current_tenant_id', (string) $tenant->id);

            return $tenant;
        }

        $tenant = Tenant::query()->create([
            'name' => $meta['pharmacy_name'] ?? config('manolya.bootstrap.pharmacy_name'),
            'slug' => 'pharmacie-'.strtolower(str()->random(6)),
            'default_currency' => config('currency.default', 'CDF'),
            'timezone' => 'Africa/Kinshasa',
            'locale' => 'fr',
            'status' => 'active',
        ]);

        app()->instance('current_tenant_id', (string) $tenant->id);

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $meta['site_name'] ?? config('manolya.bootstrap.site_name'),
            'code' => $meta['site_code'] ?? config('manolya.bootstrap.site_code'),
            'address' => null,
            'is_main' => true,
        ]);

        Warehouse::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'Réserve principale',
            'code' => 'WH-MAIN',
            'is_default' => true,
        ]);

        (new PharmacyCategorySeeder)->run();

        return $tenant;
    }

    /**
     * Recrée un compte owner pharmacie (/login) s’il n’en existe aucun.
     * Ne touche pas aux comptes déjà présents.
     */
    public function ensurePharmacyOwner(): ?User
    {
        $this->ensureRoles();
        $tenant = $this->ensureVirginPharmacyStructure();
        $site = Site::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('is_main')
            ->first();

        if ($site === null) {
            return null;
        }

        if (User::query()->where('tenant_id', $tenant->id)->exists()) {
            return null;
        }

        $email = strtolower((string) config('manolya.bootstrap.pharmacy_owner_email'));
        $name = (string) config('manolya.bootstrap.pharmacy_owner_name');
        $password = (string) config('manolya.bootstrap.owner_password');

        if ($email === '' || $name === '' || $password === '') {
            return null;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'manolya-pharma.site';
            $email = 'owner@'.$host;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            return null;
        }

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->assignRole('owner');

        return $owner->fresh(['roles']);
    }

    public function bootstrapFromConfigIfEmpty(): ?User
    {
        $this->ensureRoles();

        if (! $this->needsSetup()) {
            return null;
        }

        $password = (string) config('manolya.bootstrap.owner_password');
        $email = strtolower((string) config('manolya.bootstrap.owner_email'));
        $name = (string) config('manolya.bootstrap.owner_name');

        if ($password === '' || $email === '' || $name === '') {
            return null;
        }

        $meta = [
            'pharmacy_name' => (string) config('manolya.bootstrap.pharmacy_name'),
            'site_name' => (string) config('manolya.bootstrap.site_name'),
            'site_code' => (string) config('manolya.bootstrap.site_code'),
        ];

        // Promo d’un ancien compte owner vers super_admin (migration après 1er bootstrap)
        $existing = User::withTrashed()->where('email', $email)->first();
        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->forceFill([
                'tenant_id' => null,
                'site_id' => null,
                'name' => $name,
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();
            $existing->syncRoles(['super_admin']);
            $this->ensureVirginPharmacyStructure($meta);
            $this->ensurePharmacyOwner();

            return $existing->fresh(['roles']);
        }

        return $this->bootstrapPlatform([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            ...$meta,
        ]);
    }

    /**
     * Reset données métier + comptes pharmacie, conserve / recrée le super_admin.
     *
     * @param  array{name: string, email: string, password: string, pharmacy_name?: string}  $admin
     */
    public function factoryReset(array $admin): User
    {
        $this->wipeApplicationData();

        return $this->bootstrapPlatform($admin);
    }

    public function wipeApplicationData(): void
    {
        $tables = [
            'sale_return_lines',
            'sale_returns',
            'sale_payments',
            'sale_lines',
            'sales',
            'cash_session_day_unlocks',
            'cash_register_sessions',
            'goods_receipt_lines',
            'goods_receipts',
            'purchase_order_lines',
            'purchase_orders',
            'stock_count_lines',
            'stock_counts',
            'stock_adjustments',
            'stock_movements',
            'batches',
            'products',
            'categories',
            'suppliers',
            'expenses',
            'document_versions',
            'documents',
            'alerts',
            'audit_records',
            'report_runs',
            'login_histories',
            'sessions_registry',
            'warehouses',
            'sites',
            'model_has_roles',
            'model_has_permissions',
            'personal_access_tokens',
            'password_reset_tokens',
            'sessions',
            'users',
            'tenants',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks',
        ];

        $existing = array_values(array_filter(
            $tables,
            fn (string $table) => Schema::hasTable($table)
        ));

        if ($existing === []) {
            return;
        }

        $joined = collect($existing)->map(fn (string $t) => '"'.$t.'"')->implode(', ');
        DB::statement('TRUNCATE TABLE '.$joined.' RESTART IDENTITY CASCADE');

        Artisan::call('permission:cache-reset');
    }

    /** @deprecated Use bootstrapPlatform */
    public function createVirginPharmacy(array $owner): User
    {
        return $this->bootstrapPlatform($owner);
    }
}
