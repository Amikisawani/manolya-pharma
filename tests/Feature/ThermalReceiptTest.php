<?php

namespace Tests\Feature;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Sales\Receipts\ThermalReceiptBuilder;
use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThermalReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_page_includes_58mm_receipt_payload(): void
    {
        $sale = $this->completeDemoSale();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Show')
                ->where('receipt.brand_name', 'MANOLYA PHARMA')
                ->where('receiptPrintUrl', route('sales.receipt', $sale))
                ->has('receipt.lines')
                ->has('receipt.grand_total')
            );

        $html = $this->actingAs($owner)
            ->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('size: 58mm auto', false)
            ->assertSee('width: 100%', false)
            ->assertSee('MANOLYA PHARMA', false)
            ->assertSee($sale->number, false)
            ->assertDontSee('Tableau de bord', false);

        $html->assertSee('font-size: 12pt', false);
        $html->assertSee('font-size: 16pt', false);

        $receipt = app(ThermalReceiptBuilder::class)->fromSale($sale);
        $this->assertNotSame('', $receipt->grandTotal);
        $this->assertNotSame([], $receipt->lines);
    }

    private function completeDemoSale(): Sale
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();
        $session = app(CashRegisterSessionService::class)->open([
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
                'quantity' => '2',
                'unit_price' => (string) $product->sale_price,
                'discount_amount' => '0.00',
            ]],
            payments: [[
                'method' => 'cash',
                'provider' => null,
                'amount' => bcmul((string) $product->sale_price, '2', 2),
            ]],
            cashRegisterSessionId: (string) $session->id,
        ));

        $this->assertInstanceOf(Sale::class, $sale);

        return $sale;
    }
}
