<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Services\ProductCatalogSpreadsheet;
use App\Domain\Inventory\Services\OpeningStockService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->with('category:id,name')
            ->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('commercial_name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            })
            ->orderBy('commercial_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Catalog/Products/Index', [
            'products' => $products,
            'filters' => [
                'q' => $request->string('q')->toString(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Catalog/Products/Form', [
            'product' => null,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code', 'is_default']),
        ]);
    }

    public function store(Request $request, OpeningStockService $openingStock): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $this->validated($request);
        $data['tenant_id'] = $request->user()->tenant_id;
        if (isset($data['allocation_strategy'])) {
            $data['allocation_strategy'] = strtolower((string) $data['allocation_strategy']);
        }

        $initialQty = $data['initial_qty'] ?? null;
        $lotNumber = $data['lot_number'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;
        $warehouseId = $data['warehouse_id'] ?? null;
        unset($data['initial_qty'], $data['lot_number'], $data['expires_at'], $data['warehouse_id']);

        DB::transaction(function () use ($data, $initialQty, $lotNumber, $expiresAt, $warehouseId, $openingStock, $request): void {
            $product = Product::query()->create($data);

            if ($initialQty !== null && $initialQty !== '' && (float) $initialQty > 0) {
                $openingStock->receiveForProduct($product, [
                    'quantity' => $initialQty,
                    'lot_number' => $lotNumber,
                    'expires_at' => $expiresAt,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        $hasStock = $initialQty !== null && $initialQty !== '' && (float) $initialQty > 0;

        return redirect()->route('catalog.products.index')->with(
            'success',
            $hasStock
                ? 'Produit créé et lot mis en stock : il peut être vendu en caisse.'
                : 'Produit créé. Ajoutez un lot (Stock & lots ou un achat) avant de le vendre.'
        );
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Catalog/Products/Form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'code', 'is_default']),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $this->validated($request, $product);
        if (isset($data['allocation_strategy'])) {
            $data['allocation_strategy'] = strtolower((string) $data['allocation_strategy']);
        }
        $product->update($data);

        return redirect()->route('catalog.products.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $product->deleted_by = $request->user()->id;
        $product->delete_reason = $request->string('reason')->toString() ?: 'Suppression catalogue';
        $product->delete();

        return redirect()->route('catalog.products.index')->with('success', 'Produit archivé.');
    }

    public function export(Request $request, ProductCatalogSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $this->authorize('viewAny', Product::class);
        abort_unless($request->user()?->can('products.export') || $request->user()?->can('products.view'), 403);

        $format = strtolower($request->string('format', 'xlsx')->toString());
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        $filename = 'manolya-catalogue-'.now()->format('Ymd-His').'.'.$format;
        $path = storage_path('app/temp/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $spreadsheet->exportToFile($path, $format);

        return response()->download($path, $filename, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function template(ProductCatalogSpreadsheet $spreadsheet): BinaryFileResponse
    {
        $this->authorize('viewAny', Product::class);

        $filename = 'manolya-modele-50-medicaments.xlsx';
        $path = storage_path('app/temp/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $spreadsheet->writeSampleTemplate($path, 'xlsx');

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request, ProductCatalogSpreadsheet $spreadsheet): RedirectResponse
    {
        $this->authorize('create', Product::class);
        abort_unless($request->user()?->can('products.import') || $request->user()?->can('products.create'), 403);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if ($ext === 'xls') {
            return back()->with('error', 'Format .xls non supporté — utilisez .xlsx ou .csv.');
        }
        if (! in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', 'Formats acceptés : .xlsx, .csv');
        }

        $stored = $file->storeAs('imports', 'import-'.Str::uuid().'.'.$ext, 'local');
        if (! is_string($stored) || $stored === '') {
            return back()->with('error', 'Impossible d’enregistrer le fichier importé. Réessayez.');
        }

        $path = Storage::disk('local')->path($stored);
        @set_time_limit(180);

        try {
            $result = $spreadsheet->importFromFile(
                $path,
                (string) $request->user()->tenant_id,
                $ext,
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('catalog.products.index')
                ->with('error', 'Impossible de lire le fichier. Utilisez un .xlsx ou .csv (séparateur ;) généré depuis le modèle Manolya.');
        } finally {
            Storage::disk('local')->delete($stored);
        }

        if ($result['created'] === 0 && $result['updated'] === 0) {
            $detail = $result['errors'] !== []
                ? implode(' | ', array_slice($result['errors'], 0, 5))
                : 'aucune ligne valide (sku + nom commercial requis).';

            return redirect()
                ->route('catalog.products.index')
                ->with('error', 'Aucun produit importé : '.$detail);
        }

        $message = "Import terminé : {$result['created']} créés, {$result['updated']} mis à jour";
        if ($result['skipped'] > 0) {
            $message .= ", {$result['skipped']} ignorés";
        }
        $message .= '.';

        if ($result['errors'] !== []) {
            $message .= ' Certaines lignes ont échoué : '.implode(' | ', array_slice($result['errors'], 0, 5));
        }

        return redirect()
            ->route('catalog.products.index')
            ->with('success', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku,'.($product?->id ?? 'NULL').',id'],
            'commercial_name' => ['required', 'string', 'max:255'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'preferred_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'currency_code' => ['required', 'string', 'size:3'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'critical_stock' => ['nullable', 'numeric', 'min:0'],
            'allocation_strategy' => ['nullable', 'string', 'in:fefo,fifo,lifo,FEFO,FIFO,LIFO'],
            'description' => ['nullable', 'string'],
            'initial_qty' => ['nullable', 'numeric', 'min:0'],
            'lot_number' => ['nullable', 'string', 'max:64'],
            'expires_at' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
        ]);
    }
}
