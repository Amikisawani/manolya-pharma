<?php

namespace Tests\Feature;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleTicketPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_download_sale_ticket_pdf(): void
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

        $this->assertInstanceOf(Sale::class, $sale);

        $this->actingAs($owner)
            ->get(route('sales.ticket', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Show')
                ->where('ticketPdfUrl', route('sales.ticket', $sale))
            );
    }
}
