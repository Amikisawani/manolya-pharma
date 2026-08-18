<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Shared\Support\TenantClock;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

final class CashSessionReportQuery
{
    /**
     * @return array{date?: string, q?: string, from?: string, to?: string}
     */
    public function filters(Request $request): array
    {
        return [
            'date' => $request->string('date')->toString(),
            'q' => $request->string('q')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
    }

    /**
     * @param  array{date?: string, q?: string, from?: string, to?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = CashRegisterSession::query()
            ->with(['opener:id,name', 'closer:id,name', 'site:id,name', 'tenant:id,timezone'])
            ->withSum(['sales' => fn ($sales) => $sales->where('status', Sale::STATUS_COMPLETED)], 'grand_total')
            ->withCount(['sales as sales_count' => fn ($sales) => $sales->where('status', Sale::STATUS_COMPLETED)]);

        if (($filters['date'] ?? '') !== '') {
            $query->whereDate('business_date', $filters['date']);
        } else {
            if (($filters['from'] ?? '') !== '') {
                $query->whereDate('business_date', '>=', $filters['from']);
            }
            if (($filters['to'] ?? '') !== '') {
                $query->whereDate('business_date', '<=', $filters['to']);
            }
        }

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('opener', fn ($opener) => $opener->where('name', 'like', $term));
            });
        }

        return $query
            ->orderByDesc('business_date')
            ->orderByDesc('opened_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (CashRegisterSession $session) => $this->presentRow($session));
    }

    /**
     * @param  array{q?: string, from?: string, to?: string}  $filters
     * @return array<string, mixed>
     */
    public function detail(CashRegisterSession $session, array $filters = []): array
    {
        $session->load(['opener:id,name', 'closer:id,name', 'closureRequester:id,name', 'site:id,name', 'warehouse:id,name', 'tenant:id,name,timezone']);

        $tenant = $session->tenant;
        $salesQuery = Sale::query()
            ->with(['payments', 'cashier:id,name'])
            ->where('cash_register_session_id', $session->id)
            ->where('status', Sale::STATUS_COMPLETED);

        if (($filters['q'] ?? '') !== '') {
            $term = '%'.$filters['q'].'%';
            $salesQuery->where(function ($inner) use ($term): void {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('lines.product', fn ($product) => $product->where('commercial_name', 'like', $term));
            });
        }
        if (($filters['from'] ?? '') !== '') {
            $salesQuery->whereDate('completed_at', '>=', $filters['from']);
        }
        if (($filters['to'] ?? '') !== '') {
            $salesQuery->whereDate('completed_at', '<=', $filters['to']);
        }

        $sales = $salesQuery->orderByDesc('completed_at')->paginate(30)->withQueryString();
        $allSales = Sale::query()
            ->with('payments')
            ->where('cash_register_session_id', $session->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->get();
        $returns = SaleReturn::query()
            ->where('cash_register_session_id', $session->id)
            ->orderByDesc('processed_at')
            ->get();

        $cashSales = $allSales->sum(fn (Sale $sale) => $sale->payments->where('method', 'cash')->sum('amount'));
        $cardSales = $allSales->sum(fn (Sale $sale) => $sale->payments->where('method', 'card')->sum('amount'));
        $momoSales = $allSales->sum(fn (Sale $sale) => $sale->payments->where('method', 'mobile_money')->sum('amount'));
        $cashRefunds = $returns->where('refund_method', 'cash')->sum('refund_total');

        $sales->setCollection(
            $sales->getCollection()->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => $sale->number,
                'completed_at' => TenantClock::format($sale->completed_at, $tenant),
                'cashier_name' => $sale->cashier?->name,
                'grand_total' => (string) $sale->grand_total,
                'payments' => $sale->payments->map(fn ($payment) => [
                    'method' => $payment->method,
                    'amount' => (string) $payment->amount,
                ])->values()->all(),
            ])
        );

        return [
            'session' => $this->presentSession($session, $tenant),
            'sales' => $sales,
            'returns' => $returns->map(fn (SaleReturn $return) => [
                'id' => $return->id,
                'number' => $return->number,
                'processed_at' => TenantClock::format($return->processed_at, $tenant),
                'refund_total' => (string) $return->refund_total,
                'refund_method' => $return->refund_method,
            ])->values()->all(),
            'summary' => [
                'sales_count' => $allSales->count(),
                'returns_count' => $returns->count(),
                'grand_total' => (string) $allSales->sum('grand_total'),
                'cash_sales' => (string) $cashSales,
                'card_sales' => (string) $cardSales,
                'momo_sales' => (string) $momoSales,
                'cash_refunds' => (string) $cashRefunds,
                'expected_cash' => (string) ($session->expected_cash
                    ?? ((float) $session->opening_float + (float) $cashSales - (float) $cashRefunds)),
            ],
            'filters' => [
                'q' => $filters['q'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRow(CashRegisterSession $session): array
    {
        $tenant = $session->tenant;

        return [
            'id' => $session->id,
            'number' => $session->number,
            'business_date' => optional($session->business_date)?->format('d/m/Y')
                ?? TenantClock::format($session->opened_at, $tenant, 'd/m/Y'),
            'business_date_iso' => optional($session->business_date)?->toDateString(),
            'status' => $session->status,
            'status_label' => $this->statusLabel($session->status, $session->closeRequestWasRejected()),
            'opener_name' => $session->opener?->name,
            'site_name' => $session->site?->name,
            'opened_at' => TenantClock::format($session->opened_at, $tenant),
            'closed_at' => TenantClock::format($session->closed_at, $tenant),
            'sales_count' => (int) ($session->sales_count ?? 0),
            'sales_total' => (string) ($session->sales_sum_grand_total ?? '0'),
            'opening_float' => (string) $session->opening_float,
            'variance' => $session->variance !== null ? (string) $session->variance : null,
            'close_request_rejected' => $session->closeRequestWasRejected(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSession(CashRegisterSession $session, ?Tenant $tenant = null): array
    {
        $tenant ??= $session->tenant;

        return [
            'id' => $session->id,
            'number' => $session->number,
            'status' => $session->status,
            'status_label' => $this->statusLabel($session->status, $session->closeRequestWasRejected()),
            'business_date' => optional($session->business_date)?->format('d/m/Y'),
            'site_name' => $session->site?->name,
            'warehouse_name' => $session->warehouse?->name,
            'opener_name' => $session->opener?->name,
            'closer_name' => $session->closer?->name,
            'requester_name' => $session->closureRequester?->name,
            'opened_at' => TenantClock::format($session->opened_at, $tenant),
            'closed_at' => TenantClock::format($session->closed_at, $tenant),
            'closure_requested_at' => TenantClock::format($session->closure_requested_at, $tenant),
            'opening_float' => (string) $session->opening_float,
            'closing_counted' => $session->closing_counted !== null ? (string) $session->closing_counted : null,
            'expected_cash' => $session->expected_cash !== null ? (string) $session->expected_cash : null,
            'variance' => $session->variance !== null ? (string) $session->variance : null,
            'opening_notes' => $session->opening_notes,
            'closing_notes' => $session->closing_notes,
            'opened_by' => $session->opened_by,
            'close_request_rejected' => $session->closeRequestWasRejected(),
        ];
    }

    public function statusLabel(string $status, bool $rejected = false): string
    {
        if ($rejected && $status === CashRegisterSession::STATUS_OPEN) {
            return 'Session en cours (demande rejetée)';
        }

        return match ($status) {
            CashRegisterSession::STATUS_OPEN => 'Ouverte',
            CashRegisterSession::STATUS_CLOSURE_REQUESTED => 'Fermeture demandée',
            CashRegisterSession::STATUS_CLOSED => 'Fermée',
            default => $status,
        };
    }
}
