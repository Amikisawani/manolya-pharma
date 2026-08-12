<?php

namespace Tests\Feature;

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

    public function test_sales_export_xlsx(): void
    {
        $this->seed();
        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->get(route('sales.export'))
            ->assertOk();
    }
}
