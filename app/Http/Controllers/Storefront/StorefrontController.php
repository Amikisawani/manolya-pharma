<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ContactRequest;
use App\Models\Product;
use App\Services\ManolyaBootstrap;
use App\Services\StorefrontCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ManolyaBootstrap $bootstrap,
        private readonly StorefrontCatalog $catalog,
    ) {}

    public function home(): Response|RedirectResponse
    {
        if ($redirect = $this->setupRedirect()) {
            return $redirect;
        }

        $tenant = $this->catalog->bindPharmacyTenant();
        $featured = $tenant
            ? Product::query()
                ->with('category:id,name')
                ->orderBy('commercial_name')
                ->limit(6)
                ->get()
                ->map(fn (Product $product) => $this->catalog->presentProduct($product))
                ->values()
                ->all()
            : [];

        return Inertia::render('Storefront/Home', [
            'pharmacy' => $tenant ? $this->catalog->pharmacy($tenant) : $this->fallbackPharmacy(),
            'featured' => $featured,
        ]);
    }

    public function about(): Response|RedirectResponse
    {
        if ($redirect = $this->setupRedirect()) {
            return $redirect;
        }

        $tenant = $this->catalog->bindPharmacyTenant();

        return Inertia::render('Storefront/About', [
            'pharmacy' => $tenant ? $this->catalog->pharmacy($tenant) : $this->fallbackPharmacy(),
        ]);
    }

    public function products(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->setupRedirect()) {
            return $redirect;
        }

        $tenant = $this->catalog->bindPharmacyTenant();
        $query = trim((string) $request->string('q'));
        $categoryId = (string) $request->string('category');

        $products = $tenant
            ? Product::query()
                ->with('category:id,name')
                ->when($query !== '', fn ($builder) => $this->catalog->applyProductSearch($builder, $query))
                ->when($categoryId !== '', fn ($builder) => $builder->where('category_id', $categoryId))
                ->orderBy('commercial_name')
                ->paginate(24)
                ->withQueryString()
                ->through(fn (Product $product) => $this->catalog->presentProduct($product))
            : null;

        return Inertia::render('Storefront/Products', [
            'pharmacy' => $tenant ? $this->catalog->pharmacy($tenant) : $this->fallbackPharmacy(),
            'categories' => $tenant ? $this->catalog->categories() : [],
            'filters' => [
                'q' => $query,
                'category' => $categoryId,
            ],
            'products' => $products,
        ]);
    }

    public function contact(): Response|RedirectResponse
    {
        if ($redirect = $this->setupRedirect()) {
            return $redirect;
        }

        $tenant = $this->catalog->bindPharmacyTenant();

        return Inertia::render('Storefront/Contact', [
            'pharmacy' => $tenant ? $this->catalog->pharmacy($tenant) : $this->fallbackPharmacy(),
        ]);
    }

    public function sendContact(ContactRequest $request): RedirectResponse
    {
        if ($redirect = $this->setupRedirect()) {
            return $redirect;
        }

        $data = $request->validated();

        Log::info('storefront.contact', [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        return back()->with('success', 'Message reçu. L’équipe Manolya vous recontactera.');
    }

    private function setupRedirect(): ?RedirectResponse
    {
        return $this->bootstrap->needsSetup()
            ? redirect()->route('setup.create')
            : null;
    }

    /**
     * @return array{name: string, tagline: string, city: string, country: string, phone: string, email: string, address: string, hours: string}
     */
    private function fallbackPharmacy(): array
    {
        $storefront = config('manolya.storefront', []);

        return [
            'name' => (string) config('manolya.bootstrap.pharmacy_name', 'Pharmacie Manolya'),
            'tagline' => (string) ($storefront['tagline'] ?? 'Officine de confiance à Kinshasa'),
            'city' => (string) ($storefront['city'] ?? 'Kinshasa'),
            'country' => (string) ($storefront['country'] ?? 'République du Congo'),
            'phone' => (string) ($storefront['phone'] ?? ''),
            'email' => (string) ($storefront['email'] ?? ''),
            'address' => (string) ($storefront['address'] ?? 'Kinshasa'),
            'hours' => (string) ($storefront['hours'] ?? 'Lundi — Samedi, 8h — 19h'),
        ];
    }
}
