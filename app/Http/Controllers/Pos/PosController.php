<?php

namespace App\Http\Controllers\Pos;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Sales\Services\CompleteSaleService;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', Sale::class);

        $user = $request->user();

        $warehouse = Warehouse::query()
            ->where('is_default', true)
            ->when($user?->site_id, fn ($q, $siteId) => $q->where('site_id', $siteId))
            ->first()
            ?? Warehouse::query()->first();

        $openSession = CashRegisterSession::query()
            ->with(['site:id,name', 'warehouse:id,name'])
            ->where('opened_by', $user->id)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->first();

        return Inertia::render('Pos/Index', [
            'warehouse' => $warehouse,
            'currencyCode' => $user?->tenant?->default_currency ?? 'CDF',
            'openSession' => $openSession,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'site_id']),
        ]);
    }

    public function store(Request $request, CompleteSaleService $service): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $user = $request->user();

        $openSession = CashRegisterSession::query()
            ->where('opened_by', $user->id)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->first();

        abort_unless($openSession, 422, 'Ouvrez une session de caisse avant d’encaisser.');

        $data = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,mobile_money'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.provider' => ['nullable', 'string', 'in:orange,airtel,mtn,orange_money,airtel_money,mtn_momo,stub'],
            'payments.*.provider_ref' => ['nullable', 'string'],
            'payments.*.msisdn' => ['nullable', 'string', 'max:32'],
        ]);

        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

        $sale = $service->execute(new CompleteSaleData(
            tenantId: (string) $user->tenant_id,
            siteId: (string) ($user->site_id ?? $warehouse->site_id),
            warehouseId: (string) $warehouse->id,
            cashierId: (string) $user->id,
            currencyCode: $user->tenant?->default_currency ?? 'CDF',
            lines: array_map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'quantity' => (string) $line['quantity'],
                'unit_price' => (string) $line['unit_price'],
                'discount_amount' => (string) ($line['discount_amount'] ?? '0'),
            ], $data['lines']),
            payments: array_map(fn (array $payment) => [
                'method' => $payment['method'],
                'amount' => (string) $payment['amount'],
                'provider' => $payment['provider'] ?? null,
                'provider_ref' => $payment['provider_ref'] ?? null,
                'msisdn' => $payment['msisdn'] ?? null,
            ], $data['payments']),
            discountTotal: (string) ($data['discount_total'] ?? '0'),
            cashRegisterSessionId: (string) $openSession->id,
        ));

        return redirect()
            ->route('sales.show', ['sale' => $sale, 'print' => 1])
            ->with('success', "Vente {$sale->number} enregistrée.")
            ->with('print_receipt', true);
    }

    public function search(Request $request)
    {
        $this->authorize('create', Sale::class);

        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $like = '%'.mb_strtolower($q).'%';

        $products = Product::query()
            ->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(commercial_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(generic_name, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$like]);
            })
            ->orderBy('commercial_name')
            ->limit(20)
            ->get(['id', 'sku', 'commercial_name', 'generic_name', 'barcode', 'sale_price', 'currency_code']);

        return response()->json(['data' => $products]);
    }
}
