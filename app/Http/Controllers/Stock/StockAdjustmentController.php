<?php

namespace App\Http\Controllers\Stock;

use App\Domain\Inventory\Services\StockMutator;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function create(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('batches.adjust'), 403);

        return redirect()->route('stock.batches.index');
    }

    public function store(Request $request, StockMutator $mutator): RedirectResponse
    {
        abort_unless($request->user()?->can('batches.adjust'), 403);

        $data = $request->validate([
            'batch_id' => ['required', 'uuid', 'exists:batches,id'],
            'quantity_delta' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $delta = (string) $data['quantity_delta'];
        $absQty = ltrim($delta, '+');
        if (str_starts_with($absQty, '-')) {
            $absQty = substr($absQty, 1);
        }

        DB::transaction(function () use ($data, $user, $delta, $absQty, $mutator): void {
            $batch = Batch::query()->findOrFail($data['batch_id']);

            $adjustment = StockAdjustment::query()->create([
                'tenant_id' => $user->tenant_id,
                'batch_id' => $batch->id,
                'quantity_delta' => $delta,
                'reason' => $data['reason'],
                'status' => 'approved',
                'requested_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            $type = bccomp($delta, '0', 3) >= 0
                ? StockMovement::TYPE_IN_ADJUSTMENT
                : StockMovement::TYPE_OUT_ADJUSTMENT;

            $mutator->mutate([
                'tenant_id' => $user->tenant_id,
                'batch_id' => $batch->id,
                'type' => $type,
                'quantity' => $absQty,
                'unit_cost' => $batch->unit_cost,
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
                'user_id' => $user->id,
                'notes' => $data['reason'],
            ]);
        });

        return redirect()->route('stock.batches.index')->with('success', 'Ajustement de stock enregistré.');
    }
}
