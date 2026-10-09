<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Site;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockWorthTest extends TestCase
{
    use RefreshDatabase;

    public function test_present_stock_worth_and_expected_profit_are_shown(): void
    {
        $owner = $this->pharmacyUser();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $site = Site::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Bandal',
            'code' => 'KIN-01',
            'is_main' => true,
        ]);
        $warehouse = Warehouse::query()->create([
            'tenant_id' => $owner->tenant_id,
            'site_id' => $site->id,
            'name' => 'Principal',
            'code' => 'WH-MAIN',
            'is_default' => true,
        ]);

        $para = Product::query()->create([
            'tenant_id' => $owner->tenant_id,
            'sku' => 'PARA-WORTH',
            'commercial_name' => 'Paracétamol test',
            'purchase_price' => '1000',
            'sale_price' => '1500',
            'currency_code' => 'CDF',
            'allocation_strategy' => 'fefo',
        ]);
        Batch::query()->create([
            'tenant_id' => $owner->tenant_id,
            'product_id' => $para->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'L-PAYANT',
            'quantity_on_hand' => '10',
            'unit_cost' => '800',
            'currency_code' => 'CDF',
            'status' => Batch::STATUS_ACTIVE,
        ]);
        Batch::query()->create([
            'tenant_id' => $owner->tenant_id,
            'product_id' => $para->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'L-VIDE',
            'quantity_on_hand' => '0',
            'unit_cost' => '9999',
            'currency_code' => 'CDF',
            'status' => Batch::STATUS_DEPLETED,
        ]);
        $gone = Batch::query()->create([
            'tenant_id' => $owner->tenant_id,
            'product_id' => $para->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'L-SUPPRIME',
            'quantity_on_hand' => '50',
            'unit_cost' => '800',
            'currency_code' => 'CDF',
            'status' => Batch::STATUS_ACTIVE,
        ]);
        $gone->delete();

        $free = Product::query()->create([
            'tenant_id' => $owner->tenant_id,
            'sku' => 'BOGO-WORTH',
            'commercial_name' => 'Lot offert',
            'purchase_price' => '0',
            'sale_price' => '100',
            'currency_code' => 'CDF',
            'allocation_strategy' => 'fefo',
        ]);
        Batch::query()->create([
            'tenant_id' => $owner->tenant_id,
            'product_id' => $free->id,
            'warehouse_id' => $warehouse->id,
            'lot_number' => 'L-OFFERT',
            'quantity_on_hand' => '2',
            'unit_cost' => '0',
            'currency_code' => 'CDF',
            'status' => Batch::STATUS_ACTIVE,
        ]);

        $expected = [
            'cost' => '8000.00',
            'sale_value' => '15200.00',
            'expected_profit' => '7200.00',
            'products' => 2,
        ];

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stockWorth.cost', $expected['cost'])
                ->where('stockWorth.sale_value', $expected['sale_value'])
                ->where('stockWorth.expected_profit', $expected['expected_profit'])
                ->where('stockWorth.products', $expected['products'])
                ->where('kpis.stock_value', $expected['cost'])
                ->where('kpis.stock_sale_value', $expected['sale_value'])
                ->where('kpis.expected_profit', $expected['expected_profit'])
                ->where('kpis.stock_value', $expected['cost']));

        $this->actingAs($owner)
            ->get(route('catalog.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stockWorth.expected_profit', $expected['expected_profit']));

        $this->actingAs($owner)
            ->get(route('stock.batches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stockWorth.cost', $expected['cost'])
                ->where('stockWorth.sale_value', $expected['sale_value']));

        $this->actingAs($owner)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stockWorth.expected_profit', $expected['expected_profit']));
    }
}
