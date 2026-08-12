<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Services\ProcessSaleReturnService;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SaleReturnController extends Controller
{
    public function store(Request $request, Sale $sale, ProcessSaleReturnService $service): RedirectResponse
    {
        $this->authorize('refund', $sale);

        $data = $request->validate([
            'restock' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
            'refund_method' => ['required', 'string', 'in:cash,card,mobile_money'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $user = $request->user();
        $openSession = CashRegisterSession::query()
            ->where('opened_by', $user->id)
            ->where('status', CashRegisterSession::STATUS_OPEN)
            ->first();

        $return = $service->execute([
            'sale_id' => (string) $sale->id,
            'tenant_id' => (string) $user->tenant_id,
            'processed_by' => (string) $user->id,
            'restock' => $data['restock'] ?? true,
            'reason' => $data['reason'] ?? null,
            'refund_method' => $data['refund_method'],
            'cash_register_session_id' => $openSession?->id,
            'lines' => $data['lines'],
        ]);

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', "Retour {$return->number} enregistré (".number_format((float) $return->refund_total, 0, ',', ' ')." Fc).");
    }
}
