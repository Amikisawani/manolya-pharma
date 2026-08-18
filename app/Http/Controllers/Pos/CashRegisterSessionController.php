<?php

namespace App\Http\Controllers\Pos;

use App\Domain\Sales\Services\CashRegisterSessionService;
use App\Http\Controllers\Controller;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CashRegisterSessionController extends Controller
{
    public function index(Request $request, CashRegisterSessionService $service): Response
    {
        abort_unless($request->user()?->can('sales.pos') || $request->user()?->can('sales.view'), 403);

        $gate = $service->gateFor($request->user());

        $sessions = CashRegisterSession::query()
            ->with(['opener:id,name', 'closer:id,name', 'site:id,name'])
            ->orderByDesc('opened_at')
            ->paginate(20);

        return Inertia::render('Pos/CashSessions/Index', [
            'sessions' => $sessions,
            'openSession' => $gate['session'],
            'sessionGate' => CashRegisterSessionService::presentGate($gate),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name', 'site_id']),
        ]);
    }

    public function store(Request $request, CashRegisterSessionService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('sales.pos'), 403);

        $data = $request->validate([
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'opening_float' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $warehouse = isset($data['warehouse_id'])
            ? Warehouse::query()->find($data['warehouse_id'])
            : Warehouse::query()->where('is_default', true)->first();

        try {
            $service->open([
                'tenant_id' => (string) $user->tenant_id,
                'site_id' => (string) ($user->site_id ?? $warehouse?->site_id),
                'warehouse_id' => $warehouse?->id,
                'opened_by' => (string) $user->id,
                'opening_float' => $data['opening_float'],
                'currency_code' => $user->tenant?->default_currency ?? 'CDF',
                'opening_notes' => $data['opening_notes'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('pos.index')->with('success', 'Session de caisse ouverte.');
    }

    public function show(Request $request, CashRegisterSession $session): Response
    {
        abort_unless($request->user()?->can('sales.pos') || $request->user()?->can('sales.view'), 403);

        $session->load(['opener:id,name', 'closer:id,name', 'site:id,name', 'warehouse:id,name']);

        $sales = Sale::query()
            ->with('payments')
            ->where('cash_register_session_id', $session->id)
            ->orderByDesc('completed_at')
            ->get();

        $returns = SaleReturn::query()
            ->where('cash_register_session_id', $session->id)
            ->orderByDesc('processed_at')
            ->get();

        $cashSales = $sales->sum(fn (Sale $sale) => $sale->payments->where('method', 'cash')->sum('amount'));
        $cardSales = $sales->sum(fn (Sale $sale) => $sale->payments->where('method', 'card')->sum('amount'));
        $momoSales = $sales->sum(fn (Sale $sale) => $sale->payments->where('method', 'mobile_money')->sum('amount'));
        $cashRefunds = $returns->where('refund_method', 'cash')->sum('refund_total');

        return Inertia::render('Pos/CashSessions/Show', [
            'session' => $session,
            'sales' => $sales,
            'returns' => $returns,
            'canRequestClose' => $session->isOpen()
                && ! $session->closeRequestWasRejected()
                && ($session->opened_by === $request->user()->id || $request->user()?->canApproveCashSessions()),
            'summary' => [
                'sales_count' => $sales->count(),
                'returns_count' => $returns->count(),
                'cash_sales' => $cashSales,
                'card_sales' => $cardSales,
                'momo_sales' => $momoSales,
                'cash_refunds' => $cashRefunds,
                'expected_cash' => $session->expected_cash
                    ?? ((float) $session->opening_float + (float) $cashSales - (float) $cashRefunds),
            ],
        ]);
    }

    public function close(Request $request, CashRegisterSession $session, CashRegisterSessionService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('sales.pos'), 403);
        abort_unless(
            $session->opened_by === $request->user()->id
            || $request->user()->canApproveCashSessions(),
            403
        );

        $data = $request->validate([
            'closing_counted' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->requestClose($session, [
                'requested_by' => (string) $request->user()->id,
                'closing_counted' => $data['closing_counted'],
                'closing_notes' => $data['closing_notes'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('pos.sessions.show', $session)
            ->with('success', 'Demande de fermeture envoyée au propriétaire / admin pour confirmation.');
    }
}
