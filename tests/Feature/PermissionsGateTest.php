<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PermissionsGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(User $owner, string $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'site_id' => $owner->site_id,
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_cashier_can_open_pos_but_cannot_refund(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $cashier = $this->makeUser($owner, 'cashier', 'cashier@manolya.test');

        $this->actingAs($cashier)
            ->get(route('pos.index'))
            ->assertOk();

        $sale = Sale::query()->create([
            'tenant_id' => $owner->tenant_id,
            'site_id' => $owner->site_id,
            'warehouse_id' => Warehouse::query()->where('tenant_id', $owner->tenant_id)->value('id'),
            'cashier_id' => $owner->id,
            'number' => 'SALE-PERM-001',
            'status' => Sale::STATUS_COMPLETED,
            'currency_code' => 'CDF',
            'subtotal' => 5000,
            'discount_total' => 0,
            'grand_total' => 5000,
            'completed_at' => now(),
        ]);

        $this->actingAs($cashier)
            ->post(route('sales.returns.store', $sale), [
                'refund_method' => 'cash',
                'restock' => false,
                'lines' => [[
                    'sale_line_id' => '00000000-0000-0000-0000-000000000001',
                    'quantity' => 1,
                ]],
            ])
            ->assertForbidden();
    }

    public function test_stock_manager_cannot_approve_po_or_validate_count(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $manager = $this->makeUser($owner, 'stock_manager', 'stock@manolya.test');
        $supplier = Supplier::query()->where('code', 'DIST-01')->firstOrFail();
        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('purchasing.orders.store'), [
                'supplier_id' => $supplier->id,
                'lines' => [[
                    'product_id' => $product->id,
                    'quantity_ordered' => 10,
                    'unit_cost' => 2500,
                ]],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->latest('created_at')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('purchasing.orders.approve', $order))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('inventory.counts.store'), [
                'warehouse_id' => $warehouse->id,
                'type' => 'partial',
            ])
            ->assertRedirect();

        $count = \App\Models\StockCount::query()->latest('created_at')->firstOrFail();
        $line = $count->lines()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('inventory.counts.submit', $count), [
                'lines' => [[
                    'id' => $line->id,
                    'counted_qty' => (float) $line->expected_qty,
                ]],
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('inventory.counts.validate', $count))
            ->assertForbidden();
    }
}
