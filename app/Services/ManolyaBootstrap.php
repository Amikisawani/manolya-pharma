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
    /**
     * @param  array{name: string, email: string, password: string, pharmacy_name?: string, site_name?: string, site_code?: string}  $owner
     */
    public function ensureRoles(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    public function needsSetup(): bool
    {
        if (! Schema::hasTable('users')) {
            return true;
        }

        return User::withTrashed()->count() === 0;
    }

    /**
     * Crée une officine vierge + le premier propriétaire (owner).
     *
     * @param  array{name: string, email: string, password: string, pharmacy_name?: string, site_name?: string, site_code?: string}  $owner
     */
    public function createVirginPharmacy(array $owner): User
    {
        $this->ensureRoles();

        return DB::transaction(function () use ($owner) {
            $tenant = Tenant::query()->create([
                'name' => $owner['pharmacy_name'] ?? config('manolya.bootstrap.pharmacy_name'),
                'slug' => 'pharmacie-'.strtolower(str()->random(6)),
                'default_currency' => config('currency.default', 'CDF'),
                'timezone' => 'Africa/Kinshasa',
                'locale' => 'fr',
                'status' => 'active',
            ]);

            app()->instance('current_tenant_id', (string) $tenant->id);

            $site = Site::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $owner['site_name'] ?? config('manolya.bootstrap.site_name'),
                'code' => $owner['site_code'] ?? config('manolya.bootstrap.site_code'),
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

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'site_id' => $site->id,
                'name' => $owner['name'],
                'email' => strtolower($owner['email']),
                'password' => $owner['password'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('owner');

            (new PharmacyCategorySeeder)->run();

            return $user->fresh(['roles', 'tenant', 'site']);
        });
    }

    /**
     * Bootstrap depuis la config / env si la base est vide.
     */
    public function bootstrapFromConfigIfEmpty(): ?User
    {
        if (! $this->needsSetup()) {
            $this->ensureRoles();

            return null;
        }

        $password = (string) config('manolya.bootstrap.owner_password');
        $email = (string) config('manolya.bootstrap.owner_email');
        $name = (string) config('manolya.bootstrap.owner_name');

        if ($password === '' || $email === '' || $name === '') {
            $this->ensureRoles();

            return null;
        }

        return $this->createVirginPharmacy([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'pharmacy_name' => (string) config('manolya.bootstrap.pharmacy_name'),
            'site_name' => (string) config('manolya.bootstrap.site_name'),
            'site_code' => (string) config('manolya.bootstrap.site_code'),
        ]);
    }

    /**
     * Remet l'appli à zéro (données métier + utilisateurs), puis recrée le propriétaire.
     *
     * @param  array{name: string, email: string, password: string, pharmacy_name?: string, site_name?: string, site_code?: string}  $owner
     */
    public function factoryReset(array $owner): User
    {
        $this->wipeApplicationData();

        return $this->createVirginPharmacy($owner);
    }

    public function wipeApplicationData(): void
    {
        $tables = [
            'sale_return_lines',
            'sale_returns',
            'sale_payments',
            'sale_lines',
            'sales',
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
}
