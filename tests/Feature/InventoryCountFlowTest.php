<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_submit_and_validate_stock_count_with_adjustment(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();
        $batch = Batch::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_on_hand', '>', 0)
            ->firstOrFail();

        $expected = (float) $batch->quantity_on_hand;
        $counted = $expected - 5;

        $this->actingAs($owner)
            ->post(route('inventory.counts.store'), [
                'warehouse_id' => $warehouse->id,
                'type' => 'full',
            ])
            ->assertRedirect();

        $count = StockCount::query()->latest('created_at')->firstOrFail();
        $this->assertSame('open', $count->status);

        $line = $count->lines()->where('batch_id', $batch->id)->firstOrFail();

        $this->actingAs($owner)
            ->post(route('inventory.counts.submit', $count), [
                'lines' => [[
                    'id' => $line->id,
                    'counted_qty' => $counted,
                ]],
            ])
            ->assertRedirect();

        $count->refresh();
        $this->assertSame('submitted', $count->status);

        $this->actingAs($owner)
            ->post(route('inventory.counts.validate', $count))
            ->assertRedirect();

        $count->refresh();
        $batch->refresh();

        $this->assertSame('validated', $count->status);
        $this->assertEqualsWithDelta($counted, (float) $batch->quantity_on_hand, 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'batch_id' => $batch->id,
            'type' => StockMovement::TYPE_OUT_ADJUSTMENT,
            'reference_id' => $count->id,
        ]);
    }
}
