<?php

namespace App\Domain\Sales\Services;

use App\Domain\Inventory\Services\StockMutator;
use App\Infrastructure\Audit\AuditLogger;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ProcessSaleReturnService
{
    public function __construct(
        private readonly StockMutator $stockMutator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     sale_id: string,
     *     tenant_id: string,
     *     processed_by: string,
     *     restock?: bool,
     *     reason?: string|null,
     *     refund_method?: string,
     *     cash_register_session_id?: string|null,
     *     lines: list<array{sale_line_id: string, quantity: string|float|int}>
     * }  $payload
     */
    public function execute(array $payload): SaleReturn
    {
        if (($payload['lines'] ?? []) === []) {
            throw new InvalidArgumentException('Au moins une ligne de retour est requise.');
        }

        return DB::transaction(function () use ($payload): SaleReturn {
            /** @var Sale $sale */
            $sale = Sale::query()
                ->with('lines')
                ->whereKey($payload['sale_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($sale->status !== Sale::STATUS_COMPLETED) {
                throw new RuntimeException('Seules les ventes terminées peuvent être retournées.');
            }

            $restock = (bool) ($payload['restock'] ?? true);
            $refundTotal = '0.00';
            $returnLines = [];

            foreach ($payload['lines'] as $linePayload) {
                /** @var SaleLine $saleLine */
                $saleLine = $sale->lines->firstWhere('id', $linePayload['sale_line_id'])
                    ?? SaleLine::query()->whereKey($linePayload['sale_line_id'])->where('sale_id', $sale->id)->firstOrFail();

                $qty = $this->normalizeQty($linePayload['quantity'] ?? 0);
                if (bccomp($qty, '0', 3) <= 0) {
                    continue;
                }

                $already = (string) ($saleLine->quantity_returned ?? '0');
                $maxReturnable = bcsub((string) $saleLine->quantity, $already, 3);

                if (bccomp($qty, $maxReturnable, 3) > 0) {
                    throw new RuntimeException(
                        "Quantité de retour trop élevée pour la ligne {$saleLine->id} (max {$maxReturnable})."
                    );
                }

                $unitPrice = (string) $saleLine->unit_price;
                $lineTotal = bcmul($qty, $unitPrice, 2);
                $refundTotal = bcadd($refundTotal, $lineTotal, 2);

                $returnLines[] = [
                    'sale_line' => $saleLine,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($returnLines === []) {
                throw new InvalidArgumentException('Aucune quantité valide à retourner.');
            }

            $return = SaleReturn::query()->create([
                'tenant_id' => $payload['tenant_id'],
                'sale_id' => $sale->id,
                'cash_register_session_id' => $payload['cash_register_session_id'] ?? null,
                'number' => 'SR-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'status' => SaleReturn::STATUS_COMPLETED,
                'restock' => $restock,
                'reason' => $payload['reason'] ?? null,
                'refund_method' => $payload['refund_method'] ?? 'cash',
                'refund_total' => $refundTotal,
                'processed_by' => $payload['processed_by'],
                'processed_at' => now(),
            ]);

            foreach ($returnLines as $item) {
                /** @var SaleLine $saleLine */
                $saleLine = $item['sale_line'];

                SaleReturnLine::query()->create([
                    'tenant_id' => $payload['tenant_id'],
                    'sale_return_id' => $return->id,
                    'sale_line_id' => $saleLine->id,
                    'product_id' => $saleLine->product_id,
                    'batch_id' => $saleLine->batch_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);

                $saleLine->quantity_returned = bcadd((string) ($saleLine->quantity_returned ?? '0'), $item['quantity'], 3);
                $saleLine->save();

                if ($restock && $saleLine->batch_id) {
                    $this->stockMutator->mutate([
                        'tenant_id' => $payload['tenant_id'],
                        'batch_id' => $saleLine->batch_id,
                        'type' => StockMovement::TYPE_IN_RETURN,
                        'quantity' => $item['quantity'],
                        'unit_cost' => $saleLine->unit_cost,
                        'reference_type' => SaleReturn::class,
                        'reference_id' => $return->id,
                        'user_id' => $payload['processed_by'],
                        'notes' => 'Retour vente '.$sale->number,
                    ]);
                }
            }

            $this->auditLogger->log(
                action: 'sale.returned',
                auditable: $return,
                newValues: [
                    'sale_number' => $sale->number,
                    'refund_total' => $refundTotal,
                    'restock' => $restock,
                    'lines' => count($returnLines),
                ],
                userId: $payload['processed_by'],
                tenantId: $payload['tenant_id'],
            );

            return $return->load(['lines.product', 'sale']);
        });
    }

    private function normalizeQty(string|float|int $qty): string
    {
        return number_format((float) $qty, 3, '.', '');
    }
}
