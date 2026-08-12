<?php

namespace App\Domain\Reporting\Services;

use App\Models\Alert;
use App\Models\Batch;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OwnerReportBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function daily(Tenant $tenant, CarbonInterface $day): array
    {
        Carbon::setLocale('fr');
        $day = $day->copy()->locale('fr');
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $salesQuery = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$start, $end]);

        $salesCount = (clone $salesQuery)->count();
        $ca = (string) (clone $salesQuery)->sum('grand_total');
        $profit = (string) (clone $salesQuery)->sum('profit_total');
        $expenses = (string) Expense::query()
            ->whereBetween('spent_at', [$start, $end])
            ->sum('amount');

        $avgBasket = $salesCount > 0
            ? bcdiv($ca, (string) $salesCount, 2)
            : '0.00';

        $topProducts = $this->topProducts($start, $end, 8);
        $openAlerts = Alert::query()
            ->where('status', 'open')
            ->orderByDesc('raised_at')
            ->limit(10)
            ->get(['type', 'severity', 'title', 'raised_at']);

        $criticalProducts = $this->criticalFromBatches(8);

        $expiredLots = Batch::query()
            ->where('expires_at', '<', $day->toDateString())
            ->where('quantity_on_hand', '>', 0)
            ->orderBy('expires_at')
            ->limit(8)
            ->with('product:id,commercial_name,sku')
            ->get();

        $summary = $this->executiveSummary([
            'ca' => $ca,
            'profit' => $profit,
            'expenses' => $expenses,
            'sales_count' => $salesCount,
            'alerts' => $openAlerts->count(),
            'period_label' => $day->translatedFormat('d/m/Y'),
        ]);

        return [
            'type' => 'daily',
            'tenant' => $tenant->name,
            'currency' => $tenant->default_currency ?? 'CDF',
            'currency_label' => 'Fc',
            'day' => $day->toDateString(),
            'period_label' => $day->translatedFormat('l d F Y'),
            'ca' => $ca,
            'profit' => $profit,
            'expenses' => $expenses,
            'net' => bcsub($profit, $expenses, 2),
            'sales_count' => $salesCount,
            'avg_basket' => $avgBasket,
            'top_products' => $topProducts,
            'open_alerts' => $openAlerts->map(fn (Alert $a) => [
                'type' => $a->type,
                'severity' => $a->severity,
                'title' => $a->title,
            ])->all(),
            'critical_products' => $criticalProducts->map(fn ($p) => [
                'sku' => (string) $p->sku,
                'name' => (string) $p->commercial_name,
                'qty' => number_format((float) $p->quantity_on_hand_cache, 0, '.', ''),
                'critical' => number_format((float) $p->critical_stock, 0, '.', ''),
            ])->all(),
            'expired_lots' => $expiredLots->map(fn (Batch $b) => [
                'sku' => $b->product?->sku,
                'name' => $b->product?->commercial_name,
                'lot' => $b->lot_number,
                'qty' => (string) $b->quantity_on_hand,
                'expires_at' => optional($b->expires_at)?->toDateString(),
            ])->all(),
            'summary_lines' => $summary,
            'generated_at' => now($tenant->timezone ?? 'Africa/Kinshasa')->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function monthly(Tenant $tenant, CarbonInterface $start, CarbonInterface $end): array
    {
        Carbon::setLocale('fr');
        $start = $start->copy()->locale('fr');
        $end = $end->copy()->locale('fr');
        $prevStart = $start->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $start->copy()->subMonthNoOverflow()->endOfMonth();

        $range = [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
        $prevRange = [$prevStart->copy()->startOfDay(), $prevEnd->copy()->endOfDay()];

        $ca = (string) Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', $range)
            ->sum('grand_total');
        $profit = (string) Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', $range)
            ->sum('profit_total');
        $expenses = (string) Expense::query()
            ->whereBetween('spent_at', $range)
            ->sum('amount');
        $salesCount = Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', $range)
            ->count();

        $prevCa = (string) Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', $prevRange)
            ->sum('grand_total');
        $prevProfit = (string) Sale::query()
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereBetween('completed_at', $prevRange)
            ->sum('profit_total');
        $prevExpenses = (string) Expense::query()
            ->whereBetween('spent_at', $prevRange)
            ->sum('amount');

        $stockValue = (string) Batch::query()
            ->where('quantity_on_hand', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity_on_hand * unit_cost), 0) as value')
            ->value('value');

        $topProducts = $this->topProducts($range[0], $range[1], 10);
        $topCategories = $this->topCategories($range[0], $range[1], 6);

        $caDelta = $this->pctDelta($ca, $prevCa);
        $profitDelta = $this->pctDelta($profit, $prevProfit);

        return [
            'type' => 'monthly',
            'tenant' => $tenant->name,
            'currency' => $tenant->default_currency ?? 'CDF',
            'currency_label' => 'Fc',
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'period_label' => $start->translatedFormat('F Y'),
            'ca' => $ca,
            'profit' => $profit,
            'expenses' => $expenses,
            'net' => bcsub($profit, $expenses, 2),
            'sales_count' => $salesCount,
            'avg_basket' => $salesCount > 0 ? bcdiv($ca, (string) $salesCount, 2) : '0.00',
            'prev' => [
                'ca' => $prevCa,
                'profit' => $prevProfit,
                'expenses' => $prevExpenses,
                'net' => bcsub($prevProfit, $prevExpenses, 2),
                'period_label' => $prevStart->translatedFormat('F Y'),
            ],
            'deltas' => [
                'ca_pct' => $caDelta,
                'profit_pct' => $profitDelta,
            ],
            'stock_value' => number_format((float) $stockValue, 2, '.', ''),
            'top_products' => $topProducts,
            'top_categories' => $topCategories,
            'summary_lines' => [
                "CA {$start->translatedFormat('F Y')} : ".$this->fmt($ca).' Fc ('.$caDelta.'% vs mois précédent).',
                'Marge nette après dépenses : '.$this->fmt(bcsub($profit, $expenses, 2)).' Fc.',
                $salesCount.' ventes · panier moyen '.$this->fmt($salesCount > 0 ? bcdiv($ca, (string) $salesCount, 2) : '0').' Fc.',
                'Valeur stock fin de période : '.$this->fmt($stockValue).' Fc.',
            ],
            'generated_at' => now($tenant->timezone ?? 'Africa/Kinshasa')->toDateTimeString(),
        ];
    }

    /**
     * @return list<array{name: string, qty: string, revenue: string}>
     */
    private function topProducts(CarbonInterface $start, CarbonInterface $end, int $limit): array
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.completed_at', [$start, $end])
            ->whereNull('sales.deleted_at')
            ->groupBy('products.id', 'products.commercial_name')
            ->orderByDesc(DB::raw('SUM(sale_lines.line_total)'))
            ->limit($limit)
            ->get([
                'products.commercial_name as name',
                DB::raw('SUM(sale_lines.quantity) as qty'),
                DB::raw('SUM(sale_lines.line_total) as revenue'),
            ])
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'qty' => number_format((float) $row->qty, 0, '.', ''),
                'revenue' => number_format((float) $row->revenue, 2, '.', ''),
            ])
            ->all();
    }

    /**
     * @return list<array{name: string, revenue: string}>
     */
    private function topCategories(CarbonInterface $start, CarbonInterface $end, int $limit): array
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.completed_at', [$start, $end])
            ->whereNull('sales.deleted_at')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(sale_lines.line_total)'))
            ->limit($limit)
            ->get([
                DB::raw("COALESCE(categories.name, 'Sans catégorie') as name"),
                DB::raw('SUM(sale_lines.line_total) as revenue'),
            ])
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'revenue' => number_format((float) $row->revenue, 2, '.', ''),
            ])
            ->all();
    }

    /**
     * @param  array{ca: string, profit: string, expenses: string, sales_count: int, alerts: int, period_label: string}  $m
     * @return list<string>
     */
    private function executiveSummary(array $m): array
    {
        $lines = [
            'Journée du '.$m['period_label'].' : CA '.$this->fmt($m['ca']).' Fc pour '.$m['sales_count'].' vente(s).',
            'Marge estimée '.$this->fmt($m['profit']).' Fc · Dépenses '.$this->fmt($m['expenses']).' Fc.',
            'Résultat du jour (marge − dépenses) : '.$this->fmt(bcsub($m['profit'], $m['expenses'], 2)).' Fc.',
        ];

        if ($m['alerts'] > 0) {
            $lines[] = $m['alerts'].' alerte(s) ouverte(s) à surveiller.';
        } else {
            $lines[] = 'Aucune alerte ouverte critique listée.';
        }

        if ((float) $m['ca'] <= 0) {
            $lines[] = 'Aucune vente enregistrée sur la période — vérifier l’activité caisse.';
        }

        return $lines;
    }

    private function pctDelta(string $current, string $previous): string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return bccomp($current, '0', 2) === 0 ? '0.0' : '+100.0';
        }

        $diff = bcsub($current, $previous, 4);
        $pct = bcmul(bcdiv($diff, $previous, 4), '100', 1);

        return ((float) $pct >= 0 ? '+' : '').$pct;
    }

    private function fmt(string|float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ');
    }

    /**
     * @return Collection<int, object>
     */
    private function criticalFromBatches(int $limit): Collection
    {
        return Product::query()
            ->withSum(['batches as qty_sum' => fn ($q) => $q->where('quantity_on_hand', '>', 0)], 'quantity_on_hand')
            ->orderBy('critical_stock')
            ->get(['id', 'sku', 'commercial_name', 'critical_stock'])
            ->filter(fn (Product $p) => (float) ($p->qty_sum ?? 0) <= (float) $p->critical_stock)
            ->take($limit)
            ->values()
            ->map(fn (Product $p) => (object) [
                'sku' => $p->sku,
                'commercial_name' => $p->commercial_name,
                'quantity_on_hand_cache' => $p->qty_sum ?? 0,
                'critical_stock' => $p->critical_stock,
            ]);
    }
}
