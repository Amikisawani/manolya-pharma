<?php

namespace App\Domain\Reporting\Services;

use App\Models\Batch;
use App\Models\CashRegisterSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CashSessionCloseReportBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(CashRegisterSession $session): array
    {
        $session->loadMissing([
            'opener:id,name,email,phone',
            'closer:id,name,email,phone',
            'site:id,name',
            'warehouse:id,name',
            'tenant:id,name',
        ]);

        $sales = Sale::query()
            ->with(['lines.product:id,sku,commercial_name', 'payments'])
            ->where('cash_register_session_id', $session->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->orderBy('completed_at')
            ->get();

        $returns = SaleReturn::query()
            ->where('cash_register_session_id', $session->id)
            ->orderBy('processed_at')
            ->get();

        $cashSales = (float) $sales->sum(fn (Sale $sale) => $sale->payments->where('method', 'cash')->sum('amount'));
        $cardSales = (float) $sales->sum(fn (Sale $sale) => $sale->payments->where('method', 'card')->sum('amount'));
        $momoSales = (float) $sales->sum(fn (Sale $sale) => $sale->payments->where('method', 'mobile_money')->sum('amount'));
        $cashRefunds = (float) $returns->where('refund_method', 'cash')->sum('refund_total');
        $grandTotal = (float) $sales->sum('grand_total');

        $articles = $this->aggregateSoldArticles($sales);
        $stockAlerts = $this->stockAlerts((string) $session->tenant_id);

        $opening = (float) $session->opening_float;
        $expected = (float) ($session->expected_cash ?? ($opening + $cashSales - $cashRefunds));
        $counted = (float) ($session->closing_counted ?? 0);
        $variance = (float) ($session->variance ?? ($counted - $expected));

        return [
            'type' => 'cash_session_close',
            'generated_at' => now()->timezone($session->tenant?->timezone ?: 'Africa/Kinshasa')->format('d/m/Y H:i'),
            'tenant' => $session->tenant?->name ?? 'Manolya Pharma',
            'session' => [
                'number' => $session->number,
                'site' => $session->site?->name,
                'warehouse' => $session->warehouse?->name,
                'currency' => $session->currency_code ?: 'CDF',
                'opened_at' => optional($session->opened_at)?->timezone($session->tenant?->timezone ?: 'Africa/Kinshasa')->format('d/m/Y H:i'),
                'closed_at' => optional($session->closed_at)?->timezone($session->tenant?->timezone ?: 'Africa/Kinshasa')->format('d/m/Y H:i'),
                'opening_notes' => $session->opening_notes,
                'closing_notes' => $session->closing_notes,
            ],
            'cashier' => [
                'name' => $session->opener?->name,
                'email' => $session->opener?->email,
                'phone' => $session->opener?->phone,
            ],
            'closer' => [
                'name' => $session->closer?->name,
                'email' => $session->closer?->email,
            ],
            'cashbox' => [
                'opening_float' => $opening,
                'cash_sales' => $cashSales,
                'cash_refunds' => $cashRefunds,
                'expected_cash' => $expected,
                'closing_counted' => $counted,
                'variance' => $variance,
            ],
            'payments' => [
                'cash' => $cashSales,
                'card' => $cardSales,
                'mobile_money' => $momoSales,
                'grand_total' => $grandTotal,
                'sales_count' => $sales->count(),
                'returns_count' => $returns->count(),
            ],
            'articles' => $articles,
            'returns' => $returns->map(fn (SaleReturn $ret) => [
                'number' => $ret->number,
                'method' => $ret->refund_method,
                'total' => (float) $ret->refund_total,
                'processed_at' => optional($ret->processed_at)?->format('d/m/Y H:i'),
            ])->values()->all(),
            'stock_alerts' => $stockAlerts,
            'summary_lines' => [
                'Session '.$session->number.' clôturée par '.($session->closer?->name ?? $session->opener?->name ?? '—'),
                'Fond de caisse : '.number_format($opening, 0, ',', ' ').' Fc',
                'Espèces attendues (fond + ventes cash − remboursements) : '.number_format($expected, 0, ',', ' ').' Fc',
                'Espèces comptées : '.number_format($counted, 0, ',', ' ').' Fc · Écart : '.number_format($variance, 0, ',', ' ').' Fc',
                'Ventes : '.$sales->count().' · CA total : '.number_format($grandTotal, 0, ',', ' ').' Fc',
            ],
        ];
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return list<array{sku: string, name: string, qty: float, revenue: float}>
     */
    private function aggregateSoldArticles(Collection $sales): array
    {
        $map = [];

        foreach ($sales as $sale) {
            foreach ($sale->lines as $line) {
                $key = (string) $line->product_id;
                if (! isset($map[$key])) {
                    $map[$key] = [
                        'sku' => $line->product?->sku ?? '—',
                        'name' => $line->product?->commercial_name ?? 'Produit',
                        'qty' => 0.0,
                        'revenue' => 0.0,
                    ];
                }
                $map[$key]['qty'] += (float) $line->quantity;
                $map[$key]['revenue'] += (float) $line->line_total;
            }
        }

        $rows = array_values($map);
        usort($rows, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $rows;
    }

    /**
     * @return list<array{sku: string, name: string, qty: float, level: string, threshold: float}>
     */
    private function stockAlerts(string $tenantId): array
    {
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'sku', 'commercial_name', 'min_stock', 'critical_stock']);

        $qtyByProduct = Batch::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Batch::STATUS_ACTIVE)
            ->select('product_id', DB::raw('SUM(quantity_on_hand) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $alerts = [];

        foreach ($products as $product) {
            $qty = (float) ($qtyByProduct[$product->id] ?? 0);
            $critical = (float) ($product->critical_stock ?? 0);
            $min = (float) ($product->min_stock ?? 0);

            if ($qty <= 0) {
                $alerts[] = [
                    'sku' => $product->sku,
                    'name' => $product->commercial_name,
                    'qty' => $qty,
                    'level' => 'rupture',
                    'threshold' => 0.0,
                ];
            } elseif ($qty <= $critical) {
                $alerts[] = [
                    'sku' => $product->sku,
                    'name' => $product->commercial_name,
                    'qty' => $qty,
                    'level' => 'critique',
                    'threshold' => $critical,
                ];
            } elseif ($qty <= $min) {
                $alerts[] = [
                    'sku' => $product->sku,
                    'name' => $product->commercial_name,
                    'qty' => $qty,
                    'level' => 'bas',
                    'threshold' => $min,
                ];
            }
        }

        usort($alerts, function ($a, $b) {
            $order = ['rupture' => 0, 'critique' => 1, 'bas' => 2];

            return ($order[$a['level']] <=> $order[$b['level']]) ?: ($a['qty'] <=> $b['qty']);
        });

        return array_slice($alerts, 0, 30);
    }
}
