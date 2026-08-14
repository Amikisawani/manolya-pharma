<?php

namespace Tests\Feature;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Jobs\SendCashSessionClosedReportJob;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\CashSessionClosedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CashSessionCloseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_session_dispatches_owner_pdf_report(): void
    {
        $this->seed();

        Notification::fake();
        Storage::fake('local');

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
        app(CompleteSaleService::class)->execute(new CompleteSaleData(
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

        $expected = bcadd('10000', (string) $product->sale_price, 2);

        $this->actingAs($owner)
            ->post(route('pos.sessions.close', $session), [
                'closing_counted' => $expected,
                'closing_notes' => 'Clôture test rapport',
            ])
            ->assertRedirect(route('pos.sessions.show', $session));

        $session->refresh();
        $this->assertSame(CashRegisterSession::STATUS_CLOSED, $session->status);

        (new SendCashSessionClosedReportJob((string) $session->id))->handle(
            app(\App\Domain\Reporting\Services\CashSessionCloseReportBuilder::class),
            app(\App\Domain\Reporting\Services\CashSessionClosePdfGenerator::class),
        );

        Notification::assertSentTo($owner, CashSessionClosedMail::class);

        $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $session->number);
        Storage::disk('local')->assertExists("reports/{$owner->tenant_id}/cash-sessions/{$safeNumber}.pdf");
    }
}
