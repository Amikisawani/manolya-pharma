<?php

namespace App\Domain\Sales\Services;

use App\Application\Sales\DTOs\CompleteSaleData;
use App\Domain\Inventory\Services\BatchAllocator;
use App\Domain\Inventory\Services\StockMutator;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Audit\AuditLogger;
use App\Infrastructure\Payments\CashGateway;
use App\Infrastructure\Payments\CardManualGateway;
use App\Infrastructure\Payments\MobileMoneyGatewayResolver;
use App\Infrastructure\Payments\PaymentGateway;
use App\Infrastructure\Payments\PaymentIntent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalePayment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class CompleteSaleService
{
    public function __construct(
        private readonly BatchAllocator $allocator,
        private readonly StockMutator $stockMutator,
        private readonly AuditLogger $auditLogger,
        private readonly CashGateway $cashGateway,
        private readonly CardManualGateway $cardManualGateway,
        private readonly MobileMoneyGatewayResolver $mobileMoneyResolver,
    ) {}

    public function execute(CompleteSaleData $data): Sale
    {
        if ($data->lines === []) {
            throw new InvalidArgumentException('A sale requires at least one line.');
        }

        if ($data->payments === []) {
            throw new InvalidArgumentException('A sale requires at least one payment.');
        }

        return DB::transaction(function () use ($data): Sale {
            $sale = Sale::query()->create([
                'tenant_id' => $data->tenantId,
                'site_id' => $data->siteId,
                'warehouse_id' => $data->warehouseId,
                'cash_register_session_id' => $data->cashRegisterSessionId,
                'number' => $data->number ?? $this->generateSaleNumber(),
                'cashier_id' => $data->cashierId,
                'status' => Sale::STATUS_DRAFT,
                'subtotal' => '0.00',
                'discount_total' => (string) $data->discountTotal,
                'grand_total' => '0.00',
                'cost_total' => '0.00',
                'profit_total' => '0.00',
                'currency_code' => $data->currencyCode,
            ]);

            $subtotal = '0.00';
            $costTotal = '0.00';
            $lineCount = 0;

            foreach ($data->lines as $line) {
                $product = Product::query()->findOrFail($line['product_id']);
                $quantity = (string) $line['quantity'];
                $unitPrice = (string) $line['unit_price'];
                $discount = (string) ($line['discount_amount'] ?? '0.00');

                // Allocate then immediately decrease stock so subsequent lines see reduced qty.
                $allocations = $this->allocator->allocate(
                    productId: (string) $product->id,
                    warehouseId: $data->warehouseId,
                    quantity: $quantity,
                    strategy: $product->allocation_strategy,
                );

                foreach ($allocations as $allocation) {
                    $allocQty = $allocation['quantity'];
                    $lineGross = bcmul($unitPrice, $allocQty, 2);
                    $lineDiscountShare = bccomp($quantity, '0', 3) === 0
                        ? '0.00'
                        : bcmul($discount, bcdiv($allocQty, $quantity, 6), 2);
                    $lineTotal = bcsub($lineGross, $lineDiscountShare, 2);
                    $lineCost = bcmul($allocation['unit_cost'], $allocQty, 2);

                    SaleLine::query()->create([
                        'tenant_id' => $data->tenantId,
                        'sale_id' => $sale->id,
                        'product_id' => $allocation['product_id'],
                        'batch_id' => $allocation['batch_id'],
                        'quantity' => $allocQty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $allocation['unit_cost'],
                        'discount_amount' => $lineDiscountShare,
                        'line_total' => $lineTotal,
                    ]);

                    $this->stockMutator->mutate([
                        'tenant_id' => $data->tenantId,
                        'batch_id' => $allocation['batch_id'],
                        'type' => StockMovement::TYPE_OUT_SALE,
                        'quantity' => $allocQty,
                        'unit_cost' => $allocation['unit_cost'],
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'user_id' => $data->cashierId,
                    ]);

                    $subtotal = bcadd($subtotal, $lineGross, 2);
                    $costTotal = bcadd($costTotal, $lineCost, 2);
                    $lineCount++;
                }
            }

            $discountTotal = (string) $data->discountTotal;
            $grandTotal = bcsub($subtotal, $discountTotal, 2);
            $profitTotal = bcsub($grandTotal, $costTotal, 2);

            $paymentSum = '0.00';
            foreach ($data->payments as $payment) {
                $paymentSum = bcadd($paymentSum, (string) $payment['amount'], 2);
            }

            if (bccomp($paymentSum, $grandTotal, 2) !== 0) {
                throw new RuntimeException(
                    "Payment total [{$paymentSum}] does not match sale grand total [{$grandTotal}]."
                );
            }

            foreach ($data->payments as $payment) {
                $method = (string) $payment['method'];
                $provider = $payment['provider'] ?? null;
                $result = $this->gatewayFor($method, is_string($provider) ? $provider : null)->charge(
                    new PaymentIntent(
                        amount: Money::of((string) $payment['amount'], $data->currencyCode),
                        method: $method,
                        provider: is_string($provider) ? $provider : null,
                        reference: $payment['provider_ref'] ?? $sale->number,
                        metadata: [
                            'sale_id' => $sale->id,
                            'tenant_id' => $data->tenantId,
                            'msisdn' => $payment['msisdn'] ?? null,
                        ],
                    )
                );

                if (! $result->successful) {
                    throw new RuntimeException($result->message ?? 'Payment failed.');
                }

                SalePayment::query()->create([
                    'tenant_id' => $data->tenantId,
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'provider' => $payment['provider'] ?? $result->provider,
                    'amount' => $payment['amount'],
                    'provider_ref' => $result->providerRef,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            $sale->fill([
                'status' => Sale::STATUS_COMPLETED,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'cost_total' => $costTotal,
                'profit_total' => $profitTotal,
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'sale.completed',
                auditable: $sale,
                newValues: [
                    'number' => $sale->number,
                    'grand_total' => $sale->grand_total,
                    'currency_code' => $sale->currency_code,
                    'lines' => $lineCount,
                ],
                userId: $data->cashierId,
                tenantId: $data->tenantId,
            );

            return $sale->load(['lines', 'payments']);
        });
    }

    private function gatewayFor(string $method, ?string $provider = null): PaymentGateway
    {
        return match (strtolower($method)) {
            'cash' => $this->cashGateway,
            'card' => $this->cardManualGateway,
            'mobile_money' => $this->mobileMoneyResolver->resolve($provider),
            default => throw new InvalidArgumentException("Unsupported payment method [{$method}]."),
        };
    }

    private function generateSaleNumber(): string
    {
        return 'SL-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
