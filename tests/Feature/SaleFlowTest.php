<?php

namespace Tests\Feature;

use App\Domain\Inventory\Services\BatchAllocator;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Application\Sales\DTOs\CompleteSaleData;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    public function test_complete_sale_succeeds_when_recorded_stock_is_empty(): void
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

        $batch->forceFill([
            'quantity_on_hand' => '0',
            'status' => Batch::STATUS_DEPLETED,
        ])->save();

        $sale = $this->completeCashSale($owner, $warehouse, $product, '3');

        $this->assertSame(Sale::STATUS_COMPLETED ?? 'completed', $sale->status);
        $batch->refresh();
        $this->assertEqualsWithDelta(-3, (float) $batch->quantity_on_hand, 0.001);
        $this->assertSame(Batch::STATUS_ACTIVE, $batch->status);
    }

    public function test_complete_sale_creates_shelf_lot_when_product_has_no_batch(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();
        $product = Product::query()->create([
            'tenant_id' => $owner->tenant_id,
            'sku' => 'SHELF-ONLY',
            'commercial_name' => 'Produit rayon sans lot',
            'purchase_price' => '1000',
            'sale_price' => '1500',
            'currency_code' => 'CDF',
            'allocation_strategy' => 'fefo',
        ]);

        $sale = $this->completeCashSale($owner, $warehouse, $product, '2');

        $this->assertSame(Sale::STATUS_COMPLETED ?? 'completed', $sale->status);

        $shelfLot = Batch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('lot_number', BatchAllocator::shelfLotNumber($product))
            ->firstOrFail();

        $this->assertEqualsWithDelta(-2, (float) $shelfLot->quantity_on_hand, 0.001);
        $this->assertSame((string) $shelfLot->id, (string) $sale->lines->first()?->batch_id);
    }

    public function test_complete_sale_rejects_empty_stock_when_enforced(): void
    {
        $this->seed();
        config(['manolya.sales.enforce_stock' => true]);

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();

        Batch::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->update([
                'quantity_on_hand' => '0',
                'status' => Batch::STATUS_DEPLETED,
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->completeCashSale($owner, $warehouse, $product, '1');
    }

    private function completeCashSale(User $owner, Warehouse $warehouse, Product $product, string $quantity): Sale
    {
        $amount = bcmul((string) $product->sale_price, $quantity, 2);

        return app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: (string) ($product->currency_code ?: 'CDF'),
            discountTotal: '0.00',
            lines: [[
                'product_id' => (string) $product->id,
                'quantity' => $quantity,
                'unit_price' => (string) $product->sale_price,
                'discount_amount' => '0.00',
            ]],
            payments: [[
                'method' => 'cash',
                'provider' => null,
                'amount' => $amount,
            ]],
        ));
    }
}
