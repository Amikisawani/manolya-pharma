<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\Services\CashSessionReportQuery;
use App\Models\Batch;
use App\Models\CashRegisterSession;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CashSessionReportQuery $sessionReports): Response
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $in30Days = now()->addDays(30)->toDateString();

        $salesToday = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('completed_at', '>=', $todayStart)
            ->selectRaw('COALESCE(SUM(grand_total), 0) as ca, COALESCE(SUM(profit_total), 0) as profit')
            ->first();

        $salesMonth = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('completed_at', '>=', $monthStart)
            ->selectRaw('COALESCE(SUM(grand_total), 0) as ca, COALESCE(SUM(profit_total), 0) as profit')
            ->first();

        $expensesMonth = (string) Expense::query()
            ->where('spent_at', '>=', $monthStart)
            ->sum('amount');

        $criticalProducts = Product::query()
            ->select('products.*')
            ->selectRaw('COALESCE(SUM(batches.quantity_on_hand), 0) as stock_qty')
            ->leftJoin('batches', function ($join): void {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at')
                    ->where('batches.status', Batch::STATUS_ACTIVE);
            })
            ->groupBy('products.id')
            ->havingRaw('COALESCE(SUM(batches.quantity_on_hand), 0) <= products.critical_stock')
            ->orderBy('commercial_name')
            ->limit(10)
            ->get();

        $expiredBatches = Batch::query()
            ->with('product:id,commercial_name,sku')
            ->where('expires_at', '<', now()->toDateString())
            ->where('quantity_on_hand', '>', 0)
            ->whereIn('status', [Batch::STATUS_ACTIVE, Batch::STATUS_EXPIRED])
            ->count();

        $expiringSoon = Batch::query()
            ->whereBetween('expires_at', [now()->toDateString(), $in30Days])
            ->where('quantity_on_hand', '>', 0)
            ->where('status', Batch::STATUS_ACTIVE)
            ->count();

        $stockouts = Product::query()
            ->select('products.id')
            ->leftJoin('batches', function ($join): void {
                $join->on('batches.product_id', '=', 'products.id')
                    ->whereNull('batches.deleted_at');
            })
            ->groupBy('products.id')
            ->havingRaw('COALESCE(SUM(batches.quantity_on_hand), 0) <= 0')
            ->count();

        $stockValue = (string) Batch::query()
            ->where('quantity_on_hand', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as value')
            ->value('value');

        $topProductsToday = SaleLine::query()
            ->select('sale_lines.product_id', 'products.commercial_name', 'products.sku')
            ->selectRaw('SUM(sale_lines.quantity) as qty_sold')
            ->selectRaw('SUM(sale_lines.line_total) as revenue')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.completed_at', '>=', $todayStart)
            ->groupBy('sale_lines.product_id', 'products.commercial_name', 'products.sku')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get();

        $topCategories = Category::query()
            ->select('categories.id', 'categories.name')
            ->selectRaw('COALESCE(SUM(sale_lines.line_total), 0) as revenue')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('sale_lines', 'sale_lines.product_id', '=', 'products.id')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sales.completed_at', '>=', $monthStart)
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $pendingClosures = [];
        $rejectedClosures = [];
        $canReviewSessions = $request->user()?->canReviewCashSessions() ?? false;
        $canApproveSessions = $request->user()?->canApproveCashSessions() ?? false;
        if ($canReviewSessions) {
            $pendingClosures = CashRegisterSession::query()
                ->with(['opener:id,name', 'site:id,name', 'tenant:id,timezone'])
                ->where('status', CashRegisterSession::STATUS_CLOSURE_REQUESTED)
                ->orderByDesc('closure_requested_at')
                ->limit(8)
                ->get()
                ->map(fn (CashRegisterSession $session) => $sessionReports->presentRow($session))
                ->values()
                ->all();
            $rejectedClosures = CashRegisterSession::query()
                ->with(['opener:id,name', 'site:id,name', 'tenant:id,timezone'])
                ->rejectedOpen()
                ->orderByDesc('close_request_rejected_at')
                ->limit(8)
                ->get()
                ->map(fn (CashRegisterSession $session) => $sessionReports->presentRow($session))
                ->values()
                ->all();
        }

        return Inertia::render('Dashboard/Index', [
            'kpis' => [
                'ca_today' => (string) ($salesToday->ca ?? '0'),
                'profit_today' => (string) ($salesToday->profit ?? '0'),
                'ca_month' => (string) ($salesMonth->ca ?? '0'),
                'profit_month' => (string) ($salesMonth->profit ?? '0'),
                'expenses_month' => $expensesMonth,
                'expired_batches' => $expiredBatches,
                'expiring_soon' => $expiringSoon,
                'stockouts' => $stockouts,
                'stock_value' => $stockValue,
                'critical_count' => $criticalProducts->count(),
            ],
            'criticalProducts' => $criticalProducts,
            'topProductsToday' => $topProductsToday,
            'topCategories' => $topCategories,
            'pendingClosures' => $pendingClosures,
            'rejectedClosures' => $rejectedClosures,
            'canReviewSessions' => $canReviewSessions,
            'canApproveSessions' => $canApproveSessions,
            'chartPlaceholder' => [
                'labels' => collect(range(6, 0))->map(fn (int $d) => now()->subDays($d)->format('d/m'))->values(),
                'series' => collect(range(6, 0))->map(function (int $d) {
                    return (float) Sale::query()
                        ->where('status', Sale::STATUS_COMPLETED)
                        ->whereDate('completed_at', now()->subDays($d)->toDateString())
                        ->sum('grand_total');
                })->values(),
            ],
        ]);
    }
}
