<?php

namespace App\Domain\Sales\Services;

use App\Infrastructure\Audit\AuditLogger;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CashRegisterSessionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     tenant_id: string,
     *     site_id: string,
     *     warehouse_id?: string|null,
     *     opened_by: string,
     *     opening_float: string|float|int,
     *     currency_code?: string,
     *     opening_notes?: string|null
     * }  $payload
     */
    public function open(array $payload): CashRegisterSession
    {
        return DB::transaction(function () use ($payload): CashRegisterSession {
            $existing = CashRegisterSession::query()
                ->where('opened_by', $payload['opened_by'])
                ->where('status', CashRegisterSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new RuntimeException('Une session de caisse est déjà ouverte pour cet utilisateur.');
            }

            $session = CashRegisterSession::query()->create([
                'tenant_id' => $payload['tenant_id'],
                'site_id' => $payload['site_id'],
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'opened_by' => $payload['opened_by'],
                'number' => 'CS-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'status' => CashRegisterSession::STATUS_OPEN,
                'opening_float' => $payload['opening_float'],
                'currency_code' => $payload['currency_code'] ?? 'CDF',
                'opening_notes' => $payload['opening_notes'] ?? null,
                'opened_at' => now(),
            ]);

            $this->auditLogger->log(
                action: 'cash_session.opened',
                auditable: $session,
                newValues: [
                    'number' => $session->number,
                    'opening_float' => $session->opening_float,
                ],
                userId: $payload['opened_by'],
                tenantId: $payload['tenant_id'],
            );

            return $session;
        });
    }

    /**
     * @param  array{
     *     closed_by: string,
     *     closing_counted: string|float|int,
     *     closing_notes?: string|null
     * }  $payload
     */
    public function close(CashRegisterSession $session, array $payload): CashRegisterSession
    {
        return DB::transaction(function () use ($session, $payload): CashRegisterSession {
            /** @var CashRegisterSession $locked */
            $locked = CashRegisterSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException('Cette session est déjà clôturée.');
            }

            $cashSales = (string) DB::table('sale_payments')
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->where('sales.cash_register_session_id', $locked->id)
                ->where('sales.status', Sale::STATUS_COMPLETED)
                ->where('sale_payments.method', 'cash')
                ->whereNull('sales.deleted_at')
                ->sum('sale_payments.amount');

            $cashRefunds = (string) SaleReturn::query()
                ->where('cash_register_session_id', $locked->id)
                ->where('refund_method', 'cash')
                ->sum('refund_total');

            $expected = bcadd((string) $locked->opening_float, (string) $cashSales, 2);
            $expected = bcsub($expected, (string) $cashRefunds, 2);
            $counted = number_format((float) $payload['closing_counted'], 2, '.', '');
            $variance = bcsub($counted, $expected, 2);

            $locked->fill([
                'status' => CashRegisterSession::STATUS_CLOSED,
                'closed_by' => $payload['closed_by'],
                'closing_counted' => $counted,
                'expected_cash' => $expected,
                'variance' => $variance,
                'closing_notes' => $payload['closing_notes'] ?? null,
                'closed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'cash_session.closed',
                auditable: $locked,
                newValues: [
                    'number' => $locked->number,
                    'expected_cash' => $expected,
                    'closing_counted' => $counted,
                    'variance' => $variance,
                ],
                userId: $payload['closed_by'],
                tenantId: $locked->tenant_id,
            );

            return $locked->fresh(['opener', 'closer', 'site']);
        });
    }
}
