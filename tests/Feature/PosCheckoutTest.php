<?php

namespace Tests\Feature;

use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_cart_unit_price_without_changing_catalog(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();
        app(CashRegisterSessionService::class)->open([
            'tenant_id' => (string) $owner->tenant_id,
            'site_id' => (string) $owner->site_id,
            'warehouse_id' => (string) $warehouse->id,
            'opened_by' => (string) $owner->id,
            'opening_float' => '10000',
            'currency_code' => 'CDF',
        ]);

        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $catalogPrice = (string) $product->sale_price;
        $this->assertTrue(bccomp($catalogPrice, '0', 2) > 0);
        $this->assertTrue(bccomp($catalogPrice, '4500', 2) !== 0);

        $this->actingAs($owner)
            ->post(route('pos.store'), [
                'warehouse_id' => (string) $warehouse->id,
                'discount_total' => 0,
                'lines' => [[
                    'product_id' => (string) $product->id,
                    'quantity' => 2,
                    'unit_price' => 4500,
                    'discount_amount' => 0,
                ]],
                'payments' => [[
                    'method' => 'cash',
                    'amount' => 9000,
                ]],
            ])
            ->assertRedirect();

        $sale = Sale::query()->latest('created_at')->firstOrFail();
        $this->assertSame(Sale::STATUS_COMPLETED, $sale->status);
        $this->assertEqualsWithDelta(9000, (float) $sale->grand_total, 0.01);

        $line = SaleLine::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->assertEqualsWithDelta(4500, (float) $line->unit_price, 0.01);
        $this->assertEqualsWithDelta(2, (float) $line->quantity, 0.001);
        $this->assertEqualsWithDelta(9000, (float) $line->line_total, 0.01);

        $product->refresh();
        $this->assertEqualsWithDelta((float) $catalogPrice, (float) $product->sale_price, 0.01);
    }

    public function test_pos_page_renders_when_session_is_open(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);
        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();

        app(CashRegisterSessionService::class)->open([
            'tenant_id' => (string) $owner->tenant_id,
            'site_id' => (string) $owner->site_id,
            'warehouse_id' => (string) $warehouse->id,
            'opened_by' => (string) $owner->id,
            'opening_float' => '5000',
            'currency_code' => 'CDF',
        ]);

        $this->actingAs($owner)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pos/Index')
                ->where('sessionGate.disabled', false)
                ->has('openSession.number')
            );
    }
}
