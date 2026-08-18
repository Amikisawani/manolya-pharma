<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class ProductSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_export_catalogue_xlsx(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('catalog.products.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_owner_can_import_catalogue_xlsx(): void
    {
        $this->seed();
        Storage::fake('local');

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $path = storage_path('app/temp/test-import.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'sku', 'commercial_name', 'generic_name', 'barcode', 'manufacturer',
            'purchase_price', 'sale_price', 'currency_code', 'min_stock', 'critical_stock',
            'allocation_strategy', 'category',
        ]));
        $writer->addRow(Row::fromValues([
            'TEST-XLSX-01', 'Produit Excel Test', 'Générique', '', 'Lab',
            '1000', '2000', 'CDF', '10', '5', 'fefo', 'Antalgiques',
        ]));
        $writer->close();

        $upload = new UploadedFile($path, 'catalogue.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($owner)
            ->post(route('catalog.products.import'), ['file' => $upload])
            ->assertRedirect(route('catalog.products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-XLSX-01',
            'commercial_name' => 'Produit Excel Test',
        ]);

        $this->assertNotNull(Product::query()->where('sku', 'TEST-XLSX-01')->first()?->category_id);

        @unlink($path);
    }

    public function test_invalid_xlsx_shows_flash_error_instead_of_500(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $upload = UploadedFile::fake()->create(
            'corrompu.xlsx',
            12,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->actingAs($owner)
            ->post(route('catalog.products.import'), ['file' => $upload])
            ->assertRedirect(route('catalog.products.index'))
            ->assertSessionHas('error');
    }

    public function test_mixed_rows_import_valid_lines_and_skip_invalid_ones(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $path = storage_path('app/temp/mixed-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, implode("\n", [
            'sku;commercial_name;purchase_price;sale_price;currency_code;initial_qty;lot_number;expires_at',
            'GOOD-01;Produit bon;1000;2000;CDF;10;LOT-G;31/12/2027',
            'BAD-01;Produit date texte;1000;2000;CDF;5;LOT-B;pas-une-date',
            ';Sans SKU;1000;2000;CDF;1;LOT-X;2027-01-01',
            'GOOD-02;Produit apres erreur;1000;2000;CDF;8;LOT-H;2028-06-01',
        ]));

        $upload = new UploadedFile($path, 'mixed.csv', 'text/csv', null, true);

        $this->actingAs($owner)
            ->post(route('catalog.products.import'), ['file' => $upload])
            ->assertRedirect(route('catalog.products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', ['sku' => 'GOOD-01']);
        $this->assertDatabaseHas('products', ['sku' => 'BAD-01']);
        $this->assertDatabaseHas('products', ['sku' => 'GOOD-02']);
        $this->assertDatabaseMissing('products', ['commercial_name' => 'Sans SKU']);

        $good = Batch::query()->where('lot_number', 'LOT-G')->first();
        $this->assertNotNull($good);
        $this->assertSame('2027-12-31', $good->expires_at?->toDateString());
        $this->assertNull(Batch::query()->where('lot_number', 'LOT-B')->first()?->expires_at);

        @unlink($path);
    }

    public function test_catalog_import_artisan_command_imports_file(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $path = storage_path('app/temp/cmd-import.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, implode("\n", [
            'sku;commercial_name;purchase_price;sale_price;currency_code',
            'CMD-01;Produit commande artisan;1000;2000;CDF',
        ]));

        $this->artisan('catalog:import', [
            'path' => $path,
            'tenant' => (string) $owner->tenant_id,
            'format' => 'csv',
            '--user' => (string) $owner->id,
            '--delete' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'sku' => 'CMD-01',
            'commercial_name' => 'Produit commande artisan',
        ]);
        $this->assertFileDoesNotExist($path);
    }

    public function test_sales_export_xlsx(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('sales.export'))
            ->assertOk();
    }
}
