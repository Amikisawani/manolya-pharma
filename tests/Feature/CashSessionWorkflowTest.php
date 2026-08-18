<?php

namespace Tests\Feature;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Models\Alert;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\CashSessionCloseRequestedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CashSessionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_report_lists_sessions_and_filters_by_date(): void
    {
        $sale = $this->completeSaleWithSession();
        $session = $sale['session'];
        $owner = $sale['owner'];

        $this->actingAs($owner)
            ->get(route('reports.cash-sessions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/CashSessions/Index')
                ->has('sessions.data', 1)
                ->where('sessions.data.0.number', $session->number)
            );

        $this->actingAs($owner)
            ->get(route('reports.cash-sessions.index', ['date' => '1999-01-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sessions.data', 0));

        $this->actingAs($owner)
            ->get(route('reports.cash-sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/CashSessions/Show')
                ->where('session.number', $session->number)
                ->has('sales.data')
            );
    }

    public function test_cashier_cannot_open_report_page(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $cashier = $this->makeCashier($owner);

        $this->actingAs($cashier)
            ->get(route('reports.cash-sessions.index'))
            ->assertForbidden();
    }

    public function test_open_button_becomes_continue_then_close_requires_owner_confirmation(): void
    {
        $this->seed();
        Notification::fake();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);
        $cashier = $this->makeCashier($owner);
        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();

        $this->actingAs($cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sessionGate.state', 'open'));

        $this->actingAs($cashier)
            ->post(route('pos.sessions.store'), [
                'warehouse_id' => $warehouse->id,
                'opening_float' => 10000,
            ])
            ->assertRedirect(route('pos.index'));

        $this->actingAs($cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sessionGate.state', 'continue')
                ->where('sessionGate.label', 'Continuer la session')
                ->where('sessionGate.can_request_close', true)
            );

        $session = CashRegisterSession::query()->where('opened_by', $cashier->id)->firstOrFail();

        $this->actingAs($cashier)
            ->post(route('pos.sessions.close', $session), [
                'closing_counted' => 10000,
            ])
            ->assertRedirect(route('pos.sessions.show', $session));

        $session->refresh();
        $this->assertSame(CashRegisterSession::STATUS_CLOSURE_REQUESTED, $session->status);
        $this->assertTrue(
            Alert::query()->where('type', CashRegisterSessionService::ALERT_TYPE_CLOSE_REQUEST)->exists()
        );
        Notification::assertSentTo($owner, CashSessionCloseRequestedMail::class);

        $this->actingAs($cashier)
            ->post(route('reports.cash-sessions.confirm', $session))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('reports.cash-sessions.confirm', $session), [
                'closing_counted' => 10000,
            ])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame(CashRegisterSession::STATUS_CLOSED, $session->status);

        $this->actingAs($cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sessionGate.state', 'closed')
                ->where('sessionGate.label', 'Fermé')
                ->where('sessionGate.disabled', true)
            );

        $this->actingAs($cashier)
            ->post(route('pos.sessions.store'), [
                'warehouse_id' => $warehouse->id,
                'opening_float' => 5000,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($owner)
            ->post(route('reports.cash-sessions.unlock'), [
                'user_id' => $cashier->id,
            ])
            ->assertRedirect();

        $this->actingAs($cashier)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sessionGate.state', 'open'));

        $this->actingAs($cashier)
            ->post(route('pos.sessions.store'), [
                'warehouse_id' => $warehouse->id,
                'opening_float' => 5000,
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertSame(2, CashRegisterSession::query()->where('opened_by', $cashier->id)->count());
    }

    public function test_super_admin_can_open_cash_session_report(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $admin = User::query()->create([
            'tenant_id' => null,
            'site_id' => null,
            'name' => 'Super Admin',
            'email' => 'admin-caisse@manolya.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('admin.cash-sessions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/CashSessions/Index'));
    }

    /**
     * @return array{owner: User, session: CashRegisterSession}
     */
    private function completeSaleWithSession(): array
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

        return ['owner' => $owner, 'session' => $session];
    }

    private function makeCashier(User $owner): User
    {
        $cashier = User::query()->create([
            'tenant_id' => $owner->tenant_id,
            'site_id' => $owner->site_id,
            'name' => 'Caissier Test',
            'email' => 'cashier-session@manolya.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $cashier->assignRole('cashier');

        return $cashier;
    }
}
