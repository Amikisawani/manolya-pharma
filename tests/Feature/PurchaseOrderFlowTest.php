<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_approve_and_receive_purchase_order(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $supplier = Supplier::query()->where('code', 'DIST-01')->firstOrFail();
        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('purchasing.orders.store'), [
                'supplier_id' => $supplier->id,
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity_ordered' => 25,
                    'unit_cost' => 2500,
                ]],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->latest('created_at')->firstOrFail();
        $this->assertSame('draft', $order->status);
        $line = $order->lines()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('purchasing.orders.approve', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('approved', $order->status);

        $beforeBatches = Batch::query()->where('product_id', $product->id)->count();

        $this->actingAs($owner)
            ->post(route('purchasing.orders.receive', $order), [
                'warehouse_id' => $warehouse->id,
                'lines' => [[
                    'purchase_order_line_id' => $line->id,
                    'quantity' => 25,
                    'lot_number' => 'LOT-TEST-PO-01',
                    'expires_at' => now()->addYear()->toDateString(),
                    'unit_cost' => 2500,
                ]],
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('received', $order->status);

        $batch = Batch::query()
            ->where('product_id', $product->id)
            ->where('lot_number', 'LOT-TEST-PO-01')
            ->firstOrFail();

        $this->assertEqualsWithDelta(25, (float) $batch->quantity_on_hand, 0.001);
        $this->assertGreaterThan($beforeBatches, Batch::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $batch->id,
            'type' => StockMovement::TYPE_IN_PURCHASE,
        ]);
    }
}
