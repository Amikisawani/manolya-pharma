<?php

namespace Tests\Feature;

use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Domain\Sales\Services\ProcessSaleReturnService;
use App\Application\Sales\DTOs\CompleteSaleData;
use App\Models\Batch;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashSessionAndReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_close_session_computes_variance(): void
    {
        $this->seed();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();
        $sessions = app(CashRegisterSessionService::class);

        $session = $sessions->open([
            'tenant_id' => (string) $owner->tenant_id,
            'site_id' => (string) $owner->site_id,
            'warehouse_id' => (string) $warehouse->id,
            'opened_by' => (string) $owner->id,
            'opening_float' => '10000',
            'currency_code' => 'CDF',
        ]);

        $this->assertSame(CashRegisterSession::STATUS_OPEN, $session->status);

        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $sale = app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: 'CDF',
            discountTotal: '0.00',
            lines: [[
                'product_id' => (string) $product->id,
                'quantity' => '1',
                'unit_price' => (string) $product->sale_price,
                'discount_amount' => '0.00',
            ]],
            payments: [[
                'method' => 'cash',
                'provider' => null,
                'amount' => (string) $product->sale_price,
            ]],
            cashRegisterSessionId: (string) $session->id,
        ));

        $this->assertSame(Sale::STATUS_COMPLETED, $sale->status);

        $expected = bcadd('10000', (string) $product->sale_price, 2);
        $closed = $sessions->close($session, [
            'closed_by' => (string) $owner->id,
            'closing_counted' => bcadd($expected, '500', 2),
            'closing_notes' => 'Écart test',
        ]);

        $this->assertSame(CashRegisterSession::STATUS_CLOSED, $closed->status);
        $this->assertEquals('500.00', number_format((float) $closed->variance, 2, '.', ''));
    }

    public function test_sale_return_restocks_and_tracks_quantity(): void
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

        $sale = app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: 'CDF',
            discountTotal: '0.00',
            lines: [[
                'product_id' => (string) $product->id,
                'quantity' => '3',
                'unit_price' => (string) $product->sale_price,
                'discount_amount' => '0.00',
            ]],
            payments: [[
                'method' => 'cash',
                'provider' => null,
                'amount' => (string) ((float) $product->sale_price * 3),
            ]],
        ));

        $batch->refresh();
        $afterSale = (float) $batch->quantity_on_hand;
        $line = $sale->lines()->firstOrFail();

        $return = app(ProcessSaleReturnService::class)->execute([
            'sale_id' => (string) $sale->id,
            'tenant_id' => (string) $owner->tenant_id,
            'processed_by' => (string) $owner->id,
            'restock' => true,
            'reason' => 'Client insatisfait',
            'refund_method' => 'cash',
            'lines' => [[
                'sale_line_id' => (string) $line->id,
                'quantity' => '1',
            ]],
        ]);

        $line->refresh();
        $batch->refresh();

        $this->assertEquals('1.000', number_format((float) $line->quantity_returned, 3, '.', ''));
        $this->assertEqualsWithDelta($afterSale + 1, (float) $batch->quantity_on_hand, 0.001);
        $this->assertEquals(
            number_format((float) $product->sale_price, 2, '.', ''),
            number_format((float) $return->refund_total, 2, '.', ''),
        );
    }
}
