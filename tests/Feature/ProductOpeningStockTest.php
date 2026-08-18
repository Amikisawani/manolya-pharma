<?php

namespace Tests\Feature;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Catalog\SampleMedicationCatalog;
use App\Domain\Catalog\Services\ProductCatalogSpreadsheet;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductOpeningStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_catalog_contains_fifty_unique_medications(): void
    {
        $rows = SampleMedicationCatalog::rows();
        $skus = array_column($rows, 'sku');

        $this->assertCount(50, $rows);
        $this->assertCount(50, array_unique($skus));
    }

    public function test_creating_product_without_stock_cannot_be_sold(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();

        $this->actingAs($owner)->post(route('catalog.products.store'), [
            'sku' => 'NOSTOCK-01',
            'commercial_name' => 'Produit sans lot',
            'purchase_price' => 1000,
            'sale_price' => 2000,
            'currency_code' => 'CDF',
        ])->assertRedirect(route('catalog.products.index'));

        $product = Product::query()->where('sku', 'NOSTOCK-01')->firstOrFail();
        $this->assertSame(0, (int) Batch::query()->where('product_id', $product->id)->count());

        $this->expectException(\RuntimeException::class);
        app()->instance('current_tenant_id', (string) $owner->tenant_id);
        app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: 'CDF',
            lines: [[
                'product_id' => (string) $product->id,
                'quantity' => '1',
                'unit_price' => '2000',
                'discount_amount' => '0',
            ]],
            payments: [['method' => 'cash', 'amount' => '2000']],
        ));
    }

    public function test_creating_product_with_opening_stock_can_be_sold(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);
        $warehouse = Warehouse::query()->where('tenant_id', $owner->tenant_id)->firstOrFail();

        $this->actingAs($owner)->post(route('catalog.products.store'), [
            'sku' => 'STOCK-01',
            'commercial_name' => 'Paracétamol ouverture',
            'generic_name' => 'Paracétamol',
            'purchase_price' => 2500,
            'sale_price' => 5000,
            'currency_code' => 'CDF',
            'allocation_strategy' => 'fefo',
            'initial_qty' => 40,
            'lot_number' => 'LOT-OPEN-01',
            'expires_at' => now()->addYear()->toDateString(),
            'warehouse_id' => $warehouse->id,
        ])->assertRedirect(route('catalog.products.index'));

        $product = Product::query()->where('sku', 'STOCK-01')->firstOrFail();
        $batch = Batch::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertEquals(40, (float) $batch->quantity_on_hand);
        $this->assertSame('LOT-OPEN-01', $batch->lot_number);

        $sale = app(CompleteSaleService::class)->execute(new CompleteSaleData(
            tenantId: (string) $owner->tenant_id,
            siteId: (string) $owner->site_id,
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $owner->id,
            currencyCode: 'CDF',
            lines: [[
                'product_id' => (string) $product->id,
                'quantity' => '2',
                'unit_price' => '5000',
                'discount_amount' => '0',
            ]],
            payments: [['method' => 'cash', 'amount' => '10000']],
        ));

        $this->assertSame('completed', $sale->status);
        $batch->refresh();
        $this->assertEqualsWithDelta(38, (float) $batch->quantity_on_hand, 0.001);
    }

    public function test_owner_can_download_and_import_50_medication_template(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $this->actingAs($owner)
            ->get(route('catalog.products.template'))
            ->assertOk()
            ->assertHeader('content-disposition');

        $path = storage_path('app/temp/test-50.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        app(ProductCatalogSpreadsheet::class)->writeSampleTemplate($path);

        $upload = new UploadedFile(
            $path,
            'manolya-catalogue-50-medicaments.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($owner)
            ->post(route('catalog.products.import'), ['file' => $upload])
            ->assertRedirect(route('catalog.products.index'));

        $this->assertSame(50, Product::query()->whereIn('sku', array_column(SampleMedicationCatalog::rows(), 'sku'))->count());
        $this->assertGreaterThan(0, (float) Batch::query()->where('lot_number', 'LOT-PARA-CP-500-01')->value('quantity_on_hand'));

        @unlink($path);
    }
}
