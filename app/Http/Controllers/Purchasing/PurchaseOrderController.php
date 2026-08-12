<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Inventory\Services\StockMutator;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'creator:id,name'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchasing/Orders/Index', [
            'orders' => $orders,
            'filters' => ['status' => $request->string('status')->toString()],
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('commercial_name')->limit(200)->get(['id', 'commercial_name', 'sku', 'purchase_price']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('Purchasing/Orders/Index', [
            'mode' => 'create',
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('commercial_name')->limit(200)->get(['id', 'commercial_name', 'sku', 'purchase_price']),
            'orders' => PurchaseOrder::query()->with('supplier:id,name')->orderByDesc('created_at')->paginate(10),
            'filters' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'expected_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'lines.*.quantity_ordered' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($data, $user): void {
            $subtotal = '0.00';
            foreach ($data['lines'] as $line) {
                $subtotal = bcadd($subtotal, bcmul((string) $line['quantity_ordered'], (string) $line['unit_cost'], 2), 2);
            }

            $order = PurchaseOrder::query()->create([
                'tenant_id' => $user->tenant_id,
                'site_id' => $user->site_id,
                'supplier_id' => $data['supplier_id'],
                'number' => 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'status' => 'draft',
                'expected_at' => $data['expected_at'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'currency_code' => $user->tenant?->default_currency ?? 'CDF',
                'created_by' => $user->id,
            ]);

            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'purchase_order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'quantity_ordered' => $line['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost' => $line['unit_cost'],
                ]);
            }
        });

        return redirect()->route('purchasing.orders.index')->with('success', 'Bon de commande créé.');
    }

    public function show(PurchaseOrder $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['supplier', 'lines.product', 'goodsReceipts.lines', 'creator', 'approver']);

        return Inertia::render('Purchasing/Orders/Show', [
            'order' => $order,
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function submit(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('submit', $order);

        abort_unless($order->status === 'draft', 422, 'Seuls les brouillons peuvent être soumis.');

        $order->update([
            'status' => 'submitted',
            'ordered_at' => now(),
        ]);

        return back()->with('success', 'Commande soumise.');
    }

    public function approve(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $this->authorize('approve', $order);

        abort_unless(in_array($order->status, ['submitted', 'draft'], true), 422);

        $order->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Commande approuvée.');
    }

    public function receive(Request $request, PurchaseOrder $order, StockMutator $mutator): RedirectResponse
    {
        $this->authorize('receive', $order);

        abort_unless(in_array($order->status, ['approved', 'partially_received'], true), 422);

        $data = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.lot_number' => ['required', 'string', 'max:64'],
            'lines.*.expires_at' => ['required', 'date'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

        DB::transaction(function () use ($order, $data, $user, $warehouse, $mutator): void {
            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $user->tenant_id,
                'purchase_order_id' => $order->id,
                'number' => 'GR-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            foreach ($data['lines'] as $lineData) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = $order->lines()->whereKey($lineData['purchase_order_line_id'])->firstOrFail();
                $qty = (string) $lineData['quantity'];
                $unitCost = (string) ($lineData['unit_cost'] ?? $poLine->unit_cost);

                $batch = Batch::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'product_id' => $poLine->product_id,
                    'warehouse_id' => $warehouse->id,
                    'lot_number' => $lineData['lot_number'],
                    'expires_at' => $lineData['expires_at'],
                    'quantity_on_hand' => 0,
                    'unit_cost' => $unitCost,
                    'currency_code' => $order->currency_code,
                    'status' => Batch::STATUS_ACTIVE,
                ]);

                GoodsReceiptLine::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'goods_receipt_id' => $receipt->id,
                    'product_id' => $poLine->product_id,
                    'batch_id' => $batch->id,
                    'lot_number' => $lineData['lot_number'],
                    'expires_at' => $lineData['expires_at'],
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);

                $mutator->mutate([
                    'tenant_id' => $user->tenant_id,
                    'batch_id' => $batch->id,
                    'type' => StockMovement::TYPE_IN_PURCHASE,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $receipt->id,
                    'user_id' => $user->id,
                ]);

                $poLine->quantity_received = bcadd((string) $poLine->quantity_received, $qty, 3);
                $poLine->save();
            }

            $order->load('lines');
            $allReceived = $order->lines->every(
                fn (PurchaseOrderLine $line) => bccomp((string) $line->quantity_received, (string) $line->quantity_ordered, 3) >= 0
            );

            $order->update([
                'status' => $allReceived ? 'received' : 'partially_received',
            ]);
        });

        return back()->with('success', 'Réception enregistrée.');
    }
}
