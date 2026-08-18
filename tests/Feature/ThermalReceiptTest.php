<?php

namespace Tests\Feature;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Sales\Receipts\ThermalReceiptBuilder;
use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThermalReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_simple_sale_builds_80mm_receipt_from_recorded_data(): void
    {
        [$owner, $sale] = $this->completeSale([
            ['sku' => 'PARA-500', 'quantity' => '1'],
        ]);

        $receipt = app(ThermalReceiptBuilder::class)->fromSale($sale->fresh())->toArray();

        $this->actingAs($owner)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Show')
                ->where('receipt.sale_id', $sale->id)
                ->where('receipt.sale_number', $sale->number)
                ->where('receipt.brand_name', 'MANOLYA PHARMA')
                ->where('receipt.customer_name', 'Client comptoir')
                ->where('receipt.grand_total', '5 000 Fc')
                ->where('receipt.item_count', 1)
                ->missing('receipt.profit_total')
                ->missing('receipt.cost_total')
            );

        $this->assertSame($sale->number, $receipt['sale_number']);
        $this->assertSame('Paracétamol 500mg', $receipt['lines'][0]['name']);
        $this->assertStringContainsString('1 x', $receipt['lines'][0]['quantity_label']);
        $this->assertNotEmpty($receipt['qr_svg']);
        $this->assertStringContainsString('Bandalungwa', (string) $receipt['address']);
    }

    public function test_multi_line_sale_with_discount_tendered_cash_and_named_customer(): void
    {
        [$owner, $sale] = $this->completeSale(
            [
                ['sku' => 'PARA-500', 'quantity' => '2'],
                ['sku' => 'AMOX-500', 'quantity' => '1'],
                ['sku' => 'VITC-1000', 'quantity' => '3'],
                ['sku' => 'IBU-400', 'quantity' => '1'],
            ],
            discountTotal: '2000.00',
            customerName: 'Mme Mbemba',
            amountTendered: '60000.00',
            note: 'Livraison comptoir',
        );

        $receipt = app(ThermalReceiptBuilder::class)->fromSale($sale->fresh())->toArray();

        $this->assertSame('Mme Mbemba', $receipt['customer_name']);
        $this->assertSame('2 000 Fc', $receipt['discount']);
        $this->assertSame('3 500 Fc', $receipt['change']);
        $this->assertSame('60 000 Fc', $receipt['amount_paid']);
        $this->assertSame('Livraison comptoir', $receipt['note']);
        $this->assertGreaterThanOrEqual(4, count($receipt['lines']));
        $this->assertSame('3500.00', number_format((float) $sale->change_given, 2, '.', ''));
        $this->assertSame($sale->grand_total, number_format(
            (float) $sale->subtotal - (float) $sale->discount_total,
            2,
            '.',
            ''
        ));
    }

    public function test_long_product_name_and_high_amount_stay_on_ticket(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();
        $product->update([
            'commercial_name' => 'Paracétamol 500mg comprimé sécable boîte de 20 — laboratoire régional Kinshasa',
        ]);

        [, $sale] = $this->completeSale(
            [['sku' => 'PARA-500', 'quantity' => '10', 'unit_price' => '125500']],
            owner: $owner,
        );

        $receipt = app(ThermalReceiptBuilder::class)->fromSale($sale->fresh())->toArray();

        $this->assertStringContainsString('comprimé sécable', $receipt['lines'][0]['name']);
        $this->assertSame('1 255 000 Fc', $receipt['grand_total']);
    }

    public function test_reprint_rebuilds_original_ticket_without_creating_a_sale(): void
    {
        [$owner, $sale] = $this->completeSale([['sku' => 'PARA-500', 'quantity' => '1']]);
        $count = Sale::query()->count();

        $this->actingAs($owner)
            ->get(route('sales.reprint', $sale))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Show')
                ->where('receipt.sale_id', $sale->id)
                ->where('receipt.is_reprint', true)
                ->where('printOnLoad', true)
                ->where('receipt.grand_total', '5 000 Fc')
            );

        $this->assertSame($count, Sale::query()->count());
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'grand_total' => '5000.00',
        ]);
    }

    public function test_qr_can_be_disabled_from_site_settings(): void
    {
        [$owner, $sale] = $this->completeSale([['sku' => 'PARA-500', 'quantity' => '1']]);
        $site = Site::query()->findOrFail($sale->site_id);

        $this->actingAs($owner)->post(route('settings.sites.update', $site), [
            'name' => $site->name,
            'receipt_auto_print' => '1',
            'receipt_show_qr' => '0',
            'receipt_footer' => 'Gardez ce ticket.',
        ])->assertRedirect(route('settings.sites.index'));

        $site->refresh();
        $this->assertTrue($site->receipt_auto_print);
        $this->assertFalse($site->receipt_show_qr);
        $this->assertSame('Gardez ce ticket.', $site->receipt_footer);

        $receipt = app(ThermalReceiptBuilder::class)->fromSale($sale->fresh())->toArray();
        $this->assertFalse($receipt['show_qr']);
        $this->assertNull($receipt['qr_svg']);
        $this->assertSame('Gardez ce ticket.', $receipt['footer_message']);
    }

    public function test_pos_checkout_stores_tendered_amount_and_does_not_alter_paid_sale_total(): void
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
            'opening_float' => '0',
            'currency_code' => 'CDF',
        ]);

        $product = Product::query()->where('sku', 'PARA-500')->firstOrFail();

        $this->actingAs($owner)->post(route('pos.store'), [
            'warehouse_id' => $warehouse->id,
            'discount_total' => 0,
            'customer_name' => 'Client comptoir',
            'amount_tendered' => 10000,
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->sale_price,
                'discount_amount' => 0,
            ]],
            'payments' => [[
                'method' => 'cash',
                'amount' => $product->sale_price,
            ]],
        ])->assertRedirect();

        $sale = Sale::query()->latest('completed_at')->firstOrFail();
        $this->assertSame('5000.00', number_format((float) $sale->grand_total, 2, '.', ''));
        $this->assertSame('10000.00', number_format((float) $sale->amount_tendered, 2, '.', ''));
        $this->assertSame('5000.00', number_format((float) $sale->change_given, 2, '.', ''));
        $this->assertSame(1, (int) $sale->payments()->count());
        $this->assertSame('5000.00', number_format((float) $sale->payments()->first()->amount, 2, '.', ''));
    }

    /**
     * @param  list<array{sku: string, quantity: string, unit_price?: string}>  $items
     * @return array{0: User, 1: Sale}
     */
    private function completeSale(
        array $items,
        string $discountTotal = '0.00',
        ?string $customerName = null,
        ?string $amountTendered = null,
        ?string $note = null,
        ?User $owner = null,
    ): array {
        if ($owner === null) {
            $this->seed();
            $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        }

        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();
        $lines = [];
        $payment = '0.00';

        foreach ($items as $item) {
            $product = Product::query()->where('sku', $item['sku'])->firstOrFail();
            $unitPrice = $item['unit_price'] ?? (string) $product->sale_price;
            $lines[] = [
                'product_id' => (string) $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'discount_amount' => '0.00',
            ];
            $payment = bcadd($payment, bcmul($unitPrice, $item['quantity'], 2), 2);
        }

        $grand = bcsub($payment, $discountTotal, 2);

        $sale = app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: 'CDF',
            discountTotal: $discountTotal,
            lines: $lines,
            payments: [[
                'method' => 'cash',
                'provider' => null,
                'amount' => $grand,
            ]],
            customerName: $customerName,
            amountTendered: $amountTendered,
            note: $note,
        ));

        return [$owner, $sale];
    }
}
