<?php

namespace App\Http\Controllers\InventoryCount;

use App\Domain\Inventory\Services\StockMutator;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockCountController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('stock_counts.view'), 403);

        return Inertia::render('InventoryCount/Index', [
            'counts' => StockCount::query()
                ->with(['warehouse:id,name', 'starter:id,name'])
                ->orderByDesc('created_at')
                ->paginate(20),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('stock_counts.create'), 403);

        return Inertia::render('InventoryCount/Index', [
            'mode' => 'create',
            'counts' => StockCount::query()->with('warehouse:id,name')->orderByDesc('created_at')->paginate(10),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('stock_counts.create'), 403);

        $data = $request->validate([
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'type' => ['required', 'string', 'in:full,partial,cycle,rotating'],
        ]);

        $count = StockCount::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'warehouse_id' => $data['warehouse_id'],
            'type' => $data['type'],
            'status' => 'open',
            'started_by' => $request->user()->id,
        ]);

        $batches = Batch::query()
            ->where('warehouse_id', $data['warehouse_id'])
            ->where('quantity_on_hand', '>', 0)
            ->get();

        foreach ($batches as $batch) {
            StockCountLine::query()->create([
                'tenant_id' => $request->user()->tenant_id,
                'stock_count_id' => $count->id,
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'expected_qty' => $batch->quantity_on_hand,
                'counted_qty' => null,
                'variance' => null,
            ]);
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', 'Inventaire ouvert.');
    }

    public function show(Request $request, StockCount $count): Response
    {
        abort_unless($request->user()?->can('stock_counts.view'), 403);

        $count->load(['lines.product', 'lines.batch', 'warehouse', 'starter', 'validator']);

        return Inertia::render('InventoryCount/Show', [
            'count' => $count,
        ]);
    }

    public function submit(Request $request, StockCount $count): RedirectResponse
    {
        abort_unless($request->user()?->can('stock_counts.count'), 403);
        abort_unless($count->status === 'open', 422);

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.id' => ['required', 'uuid'],
            'lines.*.counted_qty' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data['lines'] as $lineData) {
            $line = $count->lines()->whereKey($lineData['id'])->firstOrFail();
            $counted = (string) $lineData['counted_qty'];
            $line->update([
                'counted_qty' => $counted,
                'variance' => bcsub($counted, (string) $line->expected_qty, 3),
            ]);
        }

        $count->update(['status' => 'submitted']);

        return back()->with('success', 'Comptage soumis pour validation.');
    }

    public function validateCount(Request $request, StockCount $count, StockMutator $mutator): RedirectResponse
    {
        abort_unless($request->user()?->can('stock_counts.validate'), 403);
        abort_unless($count->status === 'submitted', 422);

        DB::transaction(function () use ($count, $request, $mutator): void {
            foreach ($count->lines as $line) {
                if ($line->counted_qty === null || $line->batch_id === null) {
                    continue;
                }

                $variance = (string) ($line->variance ?? '0');
                if (bccomp($variance, '0', 3) === 0) {
                    continue;
                }

                $abs = ltrim($variance, '+');
                if (str_starts_with($abs, '-')) {
                    $abs = substr($abs, 1);
                }

                $type = bccomp($variance, '0', 3) > 0
                    ? StockMovement::TYPE_IN_ADJUSTMENT
                    : StockMovement::TYPE_OUT_ADJUSTMENT;

                $mutator->mutate([
                    'tenant_id' => $count->tenant_id,
                    'batch_id' => $line->batch_id,
                    'type' => $type,
                    'quantity' => $abs,
                    'reference_type' => StockCount::class,
                    'reference_id' => $count->id,
                    'user_id' => $request->user()->id,
                    'notes' => 'Ajustement inventaire',
                ]);
            }

            $count->update([
                'status' => 'validated',
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
            ]);
        });

        return back()->with('success', 'Inventaire validé.');
    }
}
