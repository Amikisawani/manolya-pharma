<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Pharmacie Manolya Kinshasa',
            'slug' => 'manolya-kinshasa',
            'default_currency' => 'CDF',
            'timezone' => 'Africa/Kinshasa',
            'locale' => 'fr',
            'status' => 'active',
        ]);

        app()->instance('current_tenant_id', (string) $tenant->id);

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Site Bandal',
            'code' => 'KIN-01',
            'address' => 'Bandalungwa, Kinshasa, RDC',
            'is_main' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'Réserve principale',
            'code' => 'WH-MAIN',
            'is_default' => true,
        ]);

        $owner = User::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'Propriétaire Manolya',
            'email' => 'owner@manolya.test',
            'password' => Hash::make('password'),
            'phone' => '+243810000000',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->assignRole('owner');

        $supplier = Supplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Distrimed Congo',
            'code' => 'DIST-01',
            'phone' => '+243811111111',
            'email' => 'contact@distrimed.cd',
            'address' => 'Kinshasa',
            'payment_terms' => 'Net 30',
        ]);

        $categories = [
            'Antalgiques' => Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Antalgiques']),
            'Antibiotiques' => Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Antibiotiques']),
            'Vitamines' => Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Vitamines']),
        ];

        $products = [
            [
                'sku' => 'PARA-500',
                'commercial_name' => 'Paracétamol 500mg',
                'generic_name' => 'Paracétamol',
                'category' => 'Antalgiques',
                'purchase_price' => '2500',
                'sale_price' => '5000',
                'min_stock' => '50',
                'critical_stock' => '20',
                'lot' => 'LOT-PARA-01',
                'qty' => '200',
                'expires' => now()->addMonths(18)->toDateString(),
            ],
            [
                'sku' => 'AMOX-500',
                'commercial_name' => 'Amoxicilline 500mg',
                'generic_name' => 'Amoxicilline',
                'category' => 'Antibiotiques',
                'purchase_price' => '8500',
                'sale_price' => '15000',
                'min_stock' => '30',
                'critical_stock' => '10',
                'lot' => 'LOT-AMOX-01',
                'qty' => '80',
                'expires' => now()->addMonths(10)->toDateString(),
            ],
            [
                'sku' => 'VITC-1000',
                'commercial_name' => 'Vitamine C 1000mg',
                'generic_name' => 'Acide ascorbique',
                'category' => 'Vitamines',
                'purchase_price' => '4500',
                'sale_price' => '9000',
                'min_stock' => '40',
                'critical_stock' => '15',
                'lot' => 'LOT-VITC-01',
                'qty' => '120',
                'expires' => now()->addMonths(24)->toDateString(),
            ],
            [
                'sku' => 'IBU-400',
                'commercial_name' => 'Ibuprofène 400mg',
                'generic_name' => 'Ibuprofène',
                'category' => 'Antalgiques',
                'purchase_price' => '3200',
                'sale_price' => '6500',
                'min_stock' => '40',
                'critical_stock' => '12',
                'lot' => 'LOT-IBU-01',
                'qty' => '8',
                'expires' => now()->addDays(25)->toDateString(),
            ],
        ];

        foreach ($products as $item) {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $categories[$item['category']]->id,
                'sku' => $item['sku'],
                'commercial_name' => $item['commercial_name'],
                'generic_name' => $item['generic_name'],
                'barcode' => '869'.$item['sku'],
                'manufacturer' => 'Generic Pharma',
                'preferred_supplier_id' => $supplier->id,
                'purchase_price' => $item['purchase_price'],
                'sale_price' => $item['sale_price'],
                'currency_code' => 'CDF',
                'min_stock' => $item['min_stock'],
                'critical_stock' => $item['critical_stock'],
                'allocation_strategy' => 'fefo',
            ]);

            Batch::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'lot_number' => $item['lot'],
                'manufactured_at' => now()->subMonths(2)->toDateString(),
                'expires_at' => $item['expires'],
                'quantity_on_hand' => $item['qty'],
                'unit_cost' => $item['purchase_price'],
                'currency_code' => 'CDF',
                'status' => Batch::STATUS_ACTIVE,
            ]);
        }

        \App\Models\Alert::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'stock_critical',
            'severity' => 'critical',
            'title' => 'Stock critique — Ibuprofène 400mg',
            'body' => 'La quantité disponible (8) est sous le seuil critique (12).',
            'status' => 'open',
            'raised_at' => now(),
        ]);

        \App\Models\Alert::query()->create([
            'tenant_id' => $tenant->id,
            'type' => 'expiry_soon',
            'severity' => 'warning',
            'title' => 'Expiration proche — Ibuprofène 400mg',
            'body' => 'Le lot LOT-IBU-01 expire dans moins de 30 jours.',
            'status' => 'open',
            'raised_at' => now()->subHour(),
        ]);
    }
}
