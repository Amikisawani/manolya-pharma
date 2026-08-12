<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('suppliers.view'), 403);

        $suppliers = Supplier::query()
            ->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchasing/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('suppliers.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
        ]);

        $data['tenant_id'] = $request->user()->tenant_id;
        Supplier::query()->create($data);

        return back()->with('success', 'Fournisseur créé.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()?->can('suppliers.update'), 403);

        $supplier->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($request->user()?->can('suppliers.delete'), 403);

        $supplier->deleted_by = $request->user()->id;
        $supplier->delete_reason = $request->string('reason')->toString() ?: 'Suppression fournisseur';
        $supplier->delete();

        return back()->with('success', 'Fournisseur archivé.');
    }
}
