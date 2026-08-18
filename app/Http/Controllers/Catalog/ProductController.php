<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Services\ProductCatalogSpreadsheet;
use App\Http\Controllers\Controller;
use App\Jobs\ImportCatalogJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $data = $this->validated($request);
        $data['tenant_id'] = $request->user()->tenant_id;
        if (isset($data['allocation_strategy'])) {
            $data['allocation_strategy'] = strtolower((string) $data['allocation_strategy']);
        }

        Product::query()->create($data);

        return redirect()->route('catalog.products.index')->with('success', 'Produit créé.');
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Catalog/Products/Form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
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
        $tenantId = (string) $request->user()->tenant_id;

        if (app()->runningUnitTests()) {
            try {
                $result = $spreadsheet->importFromFile($path, $tenantId, $ext);
            } catch (\Throwable $e) {
                report($e);

                return redirect()
                    ->route('catalog.products.index')
                    ->with('error', 'Impossible de lire le fichier. Utilisez un .xlsx ou .csv (séparateur ;) généré depuis le modèle Manolya.');
            } finally {
                Storage::disk('local')->delete($stored);
            }

            $message = "Import terminé : {$result['created']} créés, {$result['updated']} mis à jour";
            if (($result['stocked'] ?? 0) > 0) {
                $message .= ", {$result['stocked']} entrés en stock";
            }
            if ($result['skipped'] > 0) {
                $message .= ", {$result['skipped']} ignorés";
            }
            $message .= '.';
            if ($result['errors'] !== []) {
                $message .= ' Erreurs : '.implode(' | ', array_slice($result['errors'], 0, 5));
            }

            return redirect()
                ->route('catalog.products.index')
                ->with('success', $message);
        }

        ImportCatalogJob::dispatch($path, $tenantId, $ext);

        return redirect()
            ->route('catalog.products.index')
            ->with('success', 'Import lancé. Le site reste disponible : actualisez cette page dans quelques secondes pour voir les produits.');
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
        ]);
    }
}
