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
        $this->assertEqualsWithDelta(6000, (float) $ambroxol->sale_price, 0.01);
        $this->assertSame('Respiratoire', $ambroxol->category?->name);

        $this->assertSame(2, Batch::query()->where('product_id', $ambroxol->id)->count());
        $this->assertEqualsWithDelta(3, (float) Batch::query()->where('product_id', $ambroxol->id)->sum('quantity_on_hand'), 0.001);

        $start = Product::query()
            ->where('commercial_name', 'Start-180mg (artésunate) injectable B/1 kit')
            ->firstOrFail();

        $this->assertEqualsWithDelta(9400, (float) $start->purchase_price, 0.01);
        $this->assertEqualsWithDelta(11280, (float) $start->sale_price, 0.01);
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

    public function test_empty_existing_lots_are_refilled_from_invoice_qty(): void
    {
        $this->seed();

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
        ])->assertSuccessful();

        $lots = Batch::query()->where('lot_number', 'like', 'ACH-%')->get();
        $this->assertNotEmpty($lots);

        foreach ($lots as $lot) {
            $lot->quantity_on_hand = 0;
            $lot->status = Batch::STATUS_DEPLETED;
            $lot->save();
        }

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
        ])->assertSuccessful();

        $this->assertEqualsWithDelta(3, (float) Batch::query()
            ->whereHas('product', fn ($q) => $q->where('commercial_name', 'like', 'Ambroxol%'))
            ->sum('quantity_on_hand'), 0.001);
        $this->assertEqualsWithDelta(6, (float) Batch::query()
            ->whereHas('product', fn ($q) => $q->where('commercial_name', 'like', 'Start-%'))
            ->sum('quantity_on_hand'), 0.001);
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

    public function test_reimport_reapplies_invoice_unit_times_markup(): void
    {
        $this->seed();

        $args = [
            '--tenant' => 'manolya-kinshasa',
            '--file' => base_path('tests/Fixtures/invoice-purchases-sample.json'),
        ];

        $this->artisan('manolya:import-invoice-purchases', $args)->assertSuccessful();

        $ambroxol = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', ['ambroxol 15mg/5ml 100ml enfant'])
            ->firstOrFail();
        $ambroxol->sale_price = '99999';
        $ambroxol->save();

        $this->artisan('manolya:import-invoice-purchases', $args)->assertSuccessful();

        $ambroxol->refresh();
        $this->assertEqualsWithDelta(5000, (float) $ambroxol->purchase_price, 0.01);
        $this->assertEqualsWithDelta(6000, (float) $ambroxol->sale_price, 0.01);
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
            '--file' => InvoicePurchaseImporter::defaultDatasetPath(),
        ])->assertSuccessful();

        $this->assertSame(467, Product::query()->count());
        $this->assertSame(470, Batch::query()->where('lot_number', 'like', 'ACH-%')->count());
        $this->assertTrue(Supplier::query()->where('code', 'PHARMANS')->exists());
        $this->assertTrue(Tenant::query()->where('slug', 'manolya-kinshasa')->exists());
    }

    public function test_september_29_invoice_dataset_imports(): void
    {
        $this->seed();

        $path = database_path('data/manolya_invoices_2026-09-29.json');
        $this->assertFileExists($path);

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => $path,
        ])->assertSuccessful();

        $this->assertSame(288, Product::query()->count());
        $this->assertSame(295, Batch::query()->where('lot_number', 'like', 'ACH-%')->count());
        $this->assertTrue(Supplier::query()->where('code', 'AFRICA')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'PROMED')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'CONFIANCE')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'CAISA')->exists());

        $actin = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', ['actin adult 80/480mg 6cés / malacum'])
            ->firstOrFail();
        $this->assertEqualsWithDelta(1668.52, (float) $actin->purchase_price, 0.01);
        $this->assertEqualsWithDelta(2002, (float) $actin->sale_price, 0.01);

        $lot = Batch::query()->where('lot_number', 'like', 'ACH-218734139-%')->firstOrFail();
        $this->assertSame('2027-09-29', $lot->expires_at->toDateString());
    }

    public function test_omitting_file_imports_all_invoice_datasets(): void
    {
        $this->seed();

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
        ])->assertSuccessful();

        $this->assertSame(814, Product::query()->count());
        $this->assertSame(843, Batch::query()->where('lot_number', 'like', 'ACH-%')->count());
        $this->assertTrue(Supplier::query()->where('code', 'PHARMANS')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'AFRICA')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'CAISA')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'SANTEVIE')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'MEDICO')->exists());
        $this->assertTrue(Supplier::query()->where('code', 'SHAHIL')->exists());

        $dioral = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', ['dioral (sro) 22 gr - 10 sachets'])
            ->firstOrFail();
        $this->assertEqualsWithDelta(3525000, (float) $dioral->purchase_price, 0.01);
        $this->assertEqualsWithDelta(4230000, (float) $dioral->sale_price, 0.01);

        $effortil = Product::query()
            ->whereRaw('LOWER(commercial_name) = ?', ['effortil gttes 30 ml'])
            ->firstOrFail();
        $this->assertEqualsWithDelta(40648.64, (float) $effortil->purchase_price, 0.01);
        $this->assertEqualsWithDelta(48778, (float) $effortil->sale_price, 0.01);
        $this->assertFalse(
            Product::query()->whereRaw('LOWER(commercial_name) = ?', ['efferel gouttes 30 ml'])->exists(),
        );
    }

    public function test_blurry_compagnon_names_are_renamed_before_restock(): void
    {
        $this->seed();

        $tenant = Tenant::query()->where('slug', 'manolya-kinshasa')->firstOrFail();
        Product::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'EFFEREL-GTTES-OLD',
            'commercial_name' => 'Efferel gouttes 30 ml',
            'purchase_price' => '1',
            'sale_price' => '2',
            'currency_code' => 'CDF',
            'min_stock' => 0,
            'critical_stock' => 0,
            'allocation_strategy' => 'fefo',
        ]);

        $this->artisan('manolya:import-invoice-purchases', [
            '--tenant' => 'manolya-kinshasa',
            '--file' => database_path('data/manolya_invoices_2026-09-29.json'),
        ])->assertSuccessful();

        $this->assertSame(288, Product::query()->count());
        $renamed = Product::query()->where('sku', 'EFFEREL-GTTES-OLD')->firstOrFail();
        $this->assertSame('Effortil gttes 30 ml', $renamed->commercial_name);
        $this->assertEqualsWithDelta(40648.64, (float) $renamed->purchase_price, 0.01);
        $this->assertEqualsWithDelta(48778, (float) $renamed->sale_price, 0.01);
        $this->assertGreaterThan(0, (float) Batch::query()->where('product_id', $renamed->id)->sum('quantity_on_hand'));
    }
}
