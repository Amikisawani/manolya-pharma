<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ReportRun;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.reports.view'), 403);

        $days = collect(range(29, 0))->map(fn (int $d) => now()->subDays($d)->toDateString());

        $salesByDay = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(completed_at) as day')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as ca')
            ->selectRaw('COALESCE(SUM(profit_total), 0) as profit')
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->pluck('ca', 'day');

        $profitByDay = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw('DATE(completed_at) as day')
            ->selectRaw('COALESCE(SUM(profit_total), 0) as profit')
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->pluck('profit', 'day');

        $expensesByCategory = Expense::query()
            ->where('spent_at', '>=', now()->startOfMonth())
            ->select('category')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $caMonth = (string) Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->sum('grand_total');

        $profitMonth = (string) Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->sum('profit_total');

        $expensesMonth = (string) Expense::query()
            ->where('spent_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return Inertia::render('Finance/Index', [
            'tab' => 'overview',
            'overview' => [
                'ca_month' => $caMonth,
                'profit_month' => $profitMonth,
                'expenses_month' => $expensesMonth,
                'net_month' => bcsub($profitMonth, $expensesMonth, 2),
            ],
            'charts' => [
                'labels' => $days->map(fn (string $d) => \Illuminate\Support\Carbon::parse($d)->format('d/m'))->values(),
                'ca' => $days->map(fn (string $d) => (float) ($salesByDay[$d] ?? 0))->values(),
                'profit' => $days->map(fn (string $d) => (float) ($profitByDay[$d] ?? 0))->values(),
                'expenses_by_category' => $expensesByCategory,
            ],
            'expenses' => Expense::query()->with('recorder:id,name')->orderByDesc('spent_at')->paginate(15),
            'reports' => ReportRun::query()
                ->orderByDesc('period_start')
                ->orderByDesc('created_at')
                ->limit(12)
                ->get(['id', 'type', 'period_start', 'period_end', 'status', 'sent_at', 'disk_path', 'created_at']),
        ]);
    }
}
