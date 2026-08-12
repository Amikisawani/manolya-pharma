<?php

namespace Tests\Feature;

use App\Domain\Sales\Services\CompleteSaleService;
use App\Application\Sales\DTOs\CompleteSaleData;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_dashboard(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_complete_sale_decreases_batch_stock(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();
        $batch = Batch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();

        $before = (float) $batch->quantity_on_hand;

        $sale = app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: 'XAF',
            discountTotal: '0.00',
            lines: [[
                'product_id' => (string) $product->id,
                'quantity' => '2',
                'unit_price' => (string) $product->sale_price,
                'discount_amount' => '0.00',
            ]],
            payments: [[
                'method' => 'cash',
                'provider' => null,
                'amount' => (string) ((float) $product->sale_price * 2),
            ]],
        ));

        $this->assertSame(Sale::STATUS_COMPLETED ?? 'completed', $sale->status);
        $batch->refresh();
        $this->assertEqualsWithDelta($before - 2, (float) $batch->quantity_on_hand, 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'reference_id' => $sale->id,
            'type' => 'OUT_SALE',
        ]);
    }
}
