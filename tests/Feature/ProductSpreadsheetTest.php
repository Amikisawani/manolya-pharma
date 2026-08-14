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

        $product = Product::query()->where('sku', 'TEST-XLSX-01')->firstOrFail();
        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
        ]);
        $this->assertTrue(
            (float) \App\Models\Batch::query()->where('product_id', $product->id)->value('quantity_on_hand') > 0
        );

        @unlink($path);
    }

    public function test_owner_can_import_catalogue_with_french_headers(): void
    {
        $this->seed();
        Storage::fake('local');

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $path = storage_path('app/temp/test-import-fr.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues([
            'Nom commercial', 'SKU', 'DCI / générique', 'Catégorie', 'Fournisseur préféré',
            'Prix d’achat', 'Prix de vente', 'STOCK MIN', 'Stock critique', 'Stratégie d’allocation',
        ]));
        $writer->addRow(Row::fromValues([
            'Coartem 20/120', 'COA-001', 'Artéméther/Luméfantrine', 'Traitement du paludisme', 'Novartis',
            '1500', '2500', '10', '5', 'fefo',
        ]));
        $writer->close();

        $upload = new UploadedFile($path, 'catalogue-fr.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($owner)
            ->post(route('catalog.products.import'), ['file' => $upload])
            ->assertRedirect(route('catalog.products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'COA-001',
            'commercial_name' => 'Coartem 20/120',
            'purchase_price' => '1500',
            'sale_price' => '2500',
        ]);

        $product = Product::query()->where('sku', 'COA-001')->firstOrFail();
        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'lot_number' => 'IMP-COA-001',
        ]);

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
