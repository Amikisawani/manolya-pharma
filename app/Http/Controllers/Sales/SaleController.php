<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Reporting\Services\OperationalSpreadsheetExport;
use App\Domain\Sales\Receipts\ThermalReceiptBuilder;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->with(['cashier:id,name', 'payments'])
            ->when($request->string('q')->toString(), function ($query, string $q): void {
                $query->where('number', 'like', "%{$q}%");
            })
            ->when($request->string('from')->toString(), fn ($q, $from) => $q->whereDate('completed_at', '>=', $from))
            ->when($request->string('to')->toString(), fn ($q, $to) => $q->whereDate('completed_at', '<=', $to))
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
        ]);
    }

    public function export(Request $request, OperationalSpreadsheetExport $export): BinaryFileResponse
    {
        $this->authorize('viewAny', Sale::class);

        $filename = 'manolya-ventes-'.now()->format('Ymd-His').'.xlsx';
        $path = storage_path('app/temp/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $export->exportSalesToFile(
            $path,
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
            $request->string('q')->toString() ?: null,
        );

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function show(Request $request, Sale $sale, ThermalReceiptBuilder $receipts): Response
    {
        $this->authorize('view', $sale);

        $sale->load([
            'lines.product:id,commercial_name,sku',
            'lines.batch:id,lot_number,expires_at',
            'payments',
            'cashier:id,name',
            'site',
            'tenant:id,name,timezone',
            'warehouse:id,name',
            'returns.lines',
            'cashRegisterSession:id,number,status',
        ]);

        $sale->makeHidden(['cost_total', 'profit_total']);

        $canRefund = $request->user()?->can('refund', $sale) ?? false;
        $isReprint = $request->routeIs('sales.reprint') || $request->boolean('reprint');
        $printOnLoad = $isReprint
            || $request->boolean('print')
            || (bool) $request->session()->pull('print_receipt');

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
            'receipt' => $receipts->fromSale($sale, $isReprint)->toArray(),
            'printOnLoad' => $printOnLoad,
            'isReprint' => $isReprint,
            'canRefund' => $canRefund,
            'hasOpenSession' => CashRegisterSession::query()
                ->where('opened_by', $request->user()?->id)
                ->where('status', CashRegisterSession::STATUS_OPEN)
                ->exists(),
        ]);
    }
}
