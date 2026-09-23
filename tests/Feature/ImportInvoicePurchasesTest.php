<?php

namespace Tests\Feature;

use App\Domain\Purchasing\Services\InvoicePurchaseImporter;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Application\Sales\DTOs\CompleteSaleData;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportInvoicePurchasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_invoice_lines_as_sellable_stock(): void
    {
        $this->seed();

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
        ])->assertSuccessful();

        $ambroxol = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', ['ambroxol 15mg/5ml 100ml enfant'])
            ->firstOrFail();

        $this->assertSame('AMBROXOL-15MG-5ML-100ML-ENFANT', $ambroxol->sku);
        $this->assertEqualsWithDelta(5000, (float) $ambroxol->purchase_price, 0.01);
        $this->assertEqualsWithDelta(6879, (float) $ambroxol->sale_price, 0.01);
        $this->assertSame('Respiratoire', $ambroxol->category?->name);

        $this->assertSame(2, Batch::query()->where('product_id', $ambroxol->id)->count());
        $this->assertEqualsWithDelta(3, (float) Batch::query()->where('product_id', $ambroxol->id)->sum('quantity_on_hand'), 0.001);

        $start = Product::query()
            ->where('commercial_name', 'Start-180mg (artésunate) injectable B/1 kit')
            ->firstOrFail();

        $this->assertEqualsWithDelta(9400, (float) $start->purchase_price, 0.01);
        $this->assertEqualsWithDelta(13160, (float) $start->sale_price, 0.01);
        $this->assertSame(2, Batch::query()->where('product_id', $start->id)->count());
        $this->assertEqualsWithDelta(6, (float) Batch::query()->where('product_id', $start->id)->sum('quantity_on_hand'), 0.001);

        $this->assertTrue(Supplier::query()->where('code', 'COMPAGNON')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'AVRIL')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'UNIQUE')->exists());

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $ambroxol->id,
            'type' => StockMovement::TYPE_IN_PURCHASE,
        ]);
    }

    public function test_second_run_is_idempotent(): void
    {
        $this->seed();

        $args = [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
        ];

        $this->artisan('manolya:import-invoice-purchases', $args)->assertSuccessful();
        $this->artisan('manolya:import-invoice-purchases', $args)->assertSuccessful();

        $this->assertSame(2, Product::query()->where(function ($query): void {
            $query->where('commercial_name', 'like', 'Ambroxol%')
                ->orWhere('commercial_name', 'like', 'Start-%');
        })->count());
        $this->assertSame(4, Batch::query()->where('lot_number', 'like', 'ACH-%')->count());
        $this->assertSame(4, StockMovement::query()->where('type', StockMovement::TYPE_IN_PURCHASE)->where('notes', 'like', 'Facture%')->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->seed();

        $beforeProducts = Product::query()->count();
        $beforeBatches = Batch::query()->count();

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame($beforeProducts, Product::query()->count());
        $this->assertSame($beforeBatches, Batch::query()->count());
    }

    public function test_imported_lot_can_be_sold(): void
    {
        $this->seed();

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
        ])->assertSuccessful();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $product = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', ['ambroxol 15mg/5ml 100ml enfant'])
            ->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'WH-MAIN')->firstOrFail();
        $onHand = (float) Batch::query()->where('product_id', $product->id)->sum('quantity_on_hand');

        $sale = app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: (string) $product->currency_code,
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
        ));

        $this->assertSame(Sale::STATUS_COMPLETED ?? 'completed', $sale->status);
        $this->assertEqualsWithDelta(
            $onHand - 1,
            (float) Batch::query()->where('product_id', $product->id)->sum('quantity_on_hand'),
            0.001,
        );
    }

    public function test_real_invoice_dataset_imports(): void
    {
        $this->seed();

        $path = InvoicePurchaseImporter::defaultDatasetPath();
        $this->assertFileExists($path);

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
        ])->assertSuccessful();

        $this->assertSame(467, Product::query()->count());
        $this->assertSame(470, Batch::query()->where('lot_number', 'like', 'ACH-%')->count());
        $this->assertTrue(Supplier::query()->where('code', 'PHARMANS')->exists());
        $this->assertTrue(Tenant::query()->where('slug', 'manolya-kinshasa')->exists());
    }
}
