<?php

namespace App\Http\Controllers\Stock;

use App\Domain\Reporting\Services\OperationalSpreadsheetExport;
use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('batches.view'), 403);

        $movements = StockMovement::query()
            ->with([
                'product:id,commercial_name,sku',
                'batch:id,lot_number',
                'warehouse:id,name',
                'user:id,name',
            ])
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->whereHas('product', function ($p) use ($q): void {
                        $p->where('commercial_name', 'like', "%{$q}%")
                            ->orWhere('sku', 'like', "%{$q}%");
                    })->orWhereHas('batch', fn ($b) => $b->where('lot_number', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('occurred_at')
            ->paginate(40)
            ->withQueryString();

        return Inertia::render('Stock/Movements/Index', [
            'movements' => $movements,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'type' => $request->string('type')->toString(),
            ],
            'types' => [
                'IN_PURCHASE',
                'IN_RETURN',
                'IN_ADJUSTMENT',
                'OUT_SALE',
                'OUT_RETURN_SUPPLIER',
                'OUT_ADJUSTMENT',
                'OUT_EXPIRED',
                'TRANSFER',
            ],
        ]);
    }

    public function export(Request $request, OperationalSpreadsheetExport $export): BinaryFileResponse
    {
        abort_unless($request->user()?->can('batches.view'), 403);

        $filename = 'manolya-mouvements-'.now()->format('Ymd-His').'.xlsx';
        $path = storage_path('app/temp/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $export->exportMovementsToFile(
            $path,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
