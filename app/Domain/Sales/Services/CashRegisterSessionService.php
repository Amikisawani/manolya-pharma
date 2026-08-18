<?php

namespace App\Domain\Sales\Services;

use App\Domain\Shared\Support\TenantClock;
use App\Infrastructure\Audit\AuditLogger;
use App\Models\Alert;
use App\Models\CashRegisterSession;
use App\Models\CashSessionDayUnlock;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\CashSessionCloseRequestedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

final class CashRegisterSessionService
{
    public const ALERT_TYPE_CLOSE_REQUEST = 'cash_session_close_request';

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
            $tenant = Tenant::query()->find($payload['tenant_id']);
            $today = TenantClock::today($tenant);

            $existing = CashRegisterSession::query()
                ->where('opened_by', $payload['opened_by'])
                ->whereIn('status', [
                    CashRegisterSession::STATUS_OPEN,
                    CashRegisterSession::STATUS_CLOSURE_REQUESTED,
                ])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new RuntimeException('Une session de caisse est déjà ouverte pour cet utilisateur.');
            }

            $closedToday = CashRegisterSession::query()
                ->where('opened_by', $payload['opened_by'])
                ->whereDate('business_date', $today)
                ->where('status', CashRegisterSession::STATUS_CLOSED)
                ->lockForUpdate()
                ->exists();

            $unlocked = CashSessionDayUnlock::query()
                ->where('user_id', $payload['opened_by'])
                ->whereDate('business_date', $today)
                ->exists();

            if ($closedToday && ! $unlocked) {
                throw new RuntimeException('La session du jour est clôturée. Réouverture demain à minuit, ou par le propriétaire / l’admin.');
            }

            $session = CashRegisterSession::query()->create([
                'tenant_id' => $payload['tenant_id'],
                'site_id' => $payload['site_id'],
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'opened_by' => $payload['opened_by'],
                'number' => 'CS-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'business_date' => $today,
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
                    'business_date' => $today,
                ],
                userId: $payload['opened_by'],
                tenantId: $payload['tenant_id'],
            );

            return $session;
        });
    }

    /**
     * @return array{
     *     state: 'open'|'continue'|'closed',
     *     label: string,
     *     disabled: bool,
     *     can_request_close: bool,
     *     closure_pending: bool,
     *     business_date: string,
     *     session: CashRegisterSession|null
     * }
     */
    public function gateFor(User $user): array
    {
        $user->loadMissing('tenant');
        $today = TenantClock::today($user->tenant);

        $active = CashRegisterSession::query()
            ->with(['site:id,name', 'warehouse:id,name'])
            ->where('opened_by', $user->id)
            ->whereIn('status', [
                CashRegisterSession::STATUS_OPEN,
                CashRegisterSession::STATUS_CLOSURE_REQUESTED,
            ])
            ->first();

        if ($active) {
            $pending = $active->isClosureRequested();

            return [
                'state' => 'continue',
                'label' => 'Continuer la session',
                'disabled' => false,
                'can_request_close' => $active->isOpen(),
                'closure_pending' => $pending,
                'business_date' => $today,
                'session' => $active,
            ];
        }

        $closedToday = CashRegisterSession::query()
            ->where('opened_by', $user->id)
            ->whereDate('business_date', $today)
            ->where('status', CashRegisterSession::STATUS_CLOSED)
            ->exists();

        $unlocked = CashSessionDayUnlock::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $today)
            ->exists();

        if ($closedToday && ! $unlocked) {
            return [
                'state' => 'closed',
                'label' => 'Fermé',
                'disabled' => true,
                'can_request_close' => false,
                'closure_pending' => false,
                'business_date' => $today,
                'session' => null,
            ];
        }

        return [
            'state' => 'open',
            'label' => 'Ouvrir la session',
            'disabled' => false,
            'can_request_close' => false,
            'closure_pending' => false,
            'business_date' => $today,
            'session' => null,
        ];
    }

    /**
     * @param  array{
     *     requested_by: string,
     *     closing_counted: string|float|int,
     *     closing_notes?: string|null
     * }  $payload
     */
    public function requestClose(CashRegisterSession $session, array $payload): CashRegisterSession
    {
        $updated = DB::transaction(function () use ($session, $payload): CashRegisterSession {
            /** @var CashRegisterSession $locked */
            $locked = CashRegisterSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new RuntimeException(
                    $locked->isClosureRequested()
                        ? 'Une demande de fermeture est déjà en attente.'
                        : 'Cette session est déjà clôturée.'
                );
            }

            $totals = $this->cashTotals($locked);
            $counted = number_format((float) $payload['closing_counted'], 2, '.', '');
            $variance = bcsub($counted, $totals['expected'], 2);

            $locked->fill([
                'status' => CashRegisterSession::STATUS_CLOSURE_REQUESTED,
                'closure_requested_by' => $payload['requested_by'],
                'closure_requested_at' => now(),
                'closing_counted' => $counted,
                'expected_cash' => $totals['expected'],
                'variance' => $variance,
                'closing_notes' => $payload['closing_notes'] ?? null,
            ])->save();

            $this->raiseCloseRequestAlert($locked);

            $this->auditLogger->log(
                action: 'cash_session.closure_requested',
                auditable: $locked,
                newValues: [
                    'number' => $locked->number,
                    'closing_counted' => $counted,
                ],
                userId: $payload['requested_by'],
                tenantId: $locked->tenant_id,
            );

            return $locked->fresh(['opener', 'site', 'tenant']) ?? $locked;
        });

        $this->notifyApprovers($updated);

        return $updated;
    }

    /**
     * @param  array{
     *     closed_by: string,
     *     closing_counted?: string|float|int|null,
     *     closing_notes?: string|null
     * }  $payload
     */
    public function confirmClose(CashRegisterSession $session, array $payload): CashRegisterSession
    {
        if (! $session->isOpen() && ! $session->isClosureRequested()) {
            throw new RuntimeException('Cette session est déjà clôturée.');
        }

        $counted = $payload['closing_counted'] ?? $session->closing_counted;
        if ($counted === null || $counted === '') {
            throw new RuntimeException('Indiquez les espèces comptées pour confirmer la clôture.');
        }

        $closed = $this->close($session, [
            'closed_by' => $payload['closed_by'],
            'closing_counted' => $counted,
            'closing_notes' => $payload['closing_notes'] ?? $session->closing_notes,
        ]);

        $this->ackCloseRequestAlerts($closed);

        return $closed;
    }

    public function rejectClose(CashRegisterSession $session, string $rejectedBy): CashRegisterSession
    {
        return DB::transaction(function () use ($session, $rejectedBy): CashRegisterSession {
            /** @var CashRegisterSession $locked */
            $locked = CashRegisterSession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isClosureRequested()) {
                throw new RuntimeException('Aucune demande de fermeture à refuser.');
            }

            $locked->fill([
                'status' => CashRegisterSession::STATUS_OPEN,
                'closure_requested_by' => null,
                'closure_requested_at' => null,
            ])->save();

            $this->ackCloseRequestAlerts($locked);

            $this->auditLogger->log(
                action: 'cash_session.closure_rejected',
                auditable: $locked,
                newValues: ['number' => $locked->number],
                userId: $rejectedBy,
                tenantId: $locked->tenant_id,
            );

            return $locked->fresh(['opener', 'site']) ?? $locked;
        });
    }

    public function unlockDay(User $cashier, User $unlockedBy, ?string $businessDate = null): CashSessionDayUnlock
    {
        $cashier->loadMissing('tenant');
        $date = $businessDate ?: TenantClock::today($cashier->tenant);

        return DB::transaction(function () use ($cashier, $unlockedBy, $date): CashSessionDayUnlock {
            $unlock = CashSessionDayUnlock::query()->updateOrCreate(
                [
                    'tenant_id' => (string) $cashier->tenant_id,
                    'user_id' => (string) $cashier->id,
                    'business_date' => $date,
                ],
                [
                    'unlocked_by' => (string) $unlockedBy->id,
                ],
            );

            $this->auditLogger->log(
                action: 'cash_session.day_unlocked',
                auditable: $cashier,
                newValues: [
                    'cashier_id' => $cashier->id,
                    'business_date' => $date,
                ],
                userId: (string) $unlockedBy->id,
                tenantId: (string) $cashier->tenant_id,
            );

            return $unlock;
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

            if ($locked->isClosed()) {
                throw new RuntimeException('Cette session est déjà clôturée.');
            }

            $totals = $this->cashTotals($locked);
            $counted = number_format((float) $payload['closing_counted'], 2, '.', '');
            $variance = bcsub($counted, $totals['expected'], 2);

            $locked->fill([
                'status' => CashRegisterSession::STATUS_CLOSED,
                'closed_by' => $payload['closed_by'],
                'closing_counted' => $counted,
                'expected_cash' => $totals['expected'],
                'variance' => $variance,
                'closing_notes' => $payload['closing_notes'] ?? $locked->closing_notes,
                'closed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                action: 'cash_session.closed',
                auditable: $locked,
                newValues: [
                    'number' => $locked->number,
                    'expected_cash' => $totals['expected'],
                    'closing_counted' => $counted,
                    'variance' => $variance,
                ],
                userId: $payload['closed_by'],
                tenantId: $locked->tenant_id,
            );

            return $locked->fresh(['opener', 'closer', 'site']) ?? $locked;
        });
    }

    /**
     * @return array{cash_sales: string, cash_refunds: string, expected: string}
     */
    private function cashTotals(CashRegisterSession $session): array
    {
        $cashSales = (string) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.cash_register_session_id', $session->id)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->where('sale_payments.method', 'cash')
            ->whereNull('sales.deleted_at')
            ->sum('sale_payments.amount');

        $cashRefunds = (string) SaleReturn::query()
            ->where('cash_register_session_id', $session->id)
            ->where('refund_method', 'cash')
            ->sum('refund_total');

        $expected = bcadd((string) $session->opening_float, (string) $cashSales, 2);
        $expected = bcsub($expected, (string) $cashRefunds, 2);

        return [
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'expected' => $expected,
        ];
    }

    private function raiseCloseRequestAlert(CashRegisterSession $session): void
    {
        $cashierName = (string) ($session->opener?->name ?: 'Caisse');
        $day = $session->business_date
            ? $session->business_date->format('d/m/Y')
            : '';

        Alert::query()->create([
            'tenant_id' => $session->tenant_id,
            'type' => self::ALERT_TYPE_CLOSE_REQUEST,
            'severity' => 'warning',
            'title' => 'Demande de fermeture de caisse '.$session->number,
            'body' => 'Le caissier '.$cashierName.' demande la clôture de la session du '.$day.'. Confirmez pour verrouiller la caisse.',
            'reference_type' => $session->getMorphClass(),
            'reference_id' => $session->id,
            'status' => 'open',
            'raised_at' => now(),
        ]);
    }

    private function ackCloseRequestAlerts(CashRegisterSession $session): void
    {
        Alert::query()
            ->where('type', self::ALERT_TYPE_CLOSE_REQUEST)
            ->where('reference_id', $session->id)
            ->where('status', 'open')
            ->update([
                'status' => 'acked',
                'acked_at' => now(),
                'acked_by' => auth()->id(),
            ]);
    }

    private function notifyApprovers(CashRegisterSession $session): void
    {
        $tenant = $session->tenant ?? Tenant::query()->find($session->tenant_id);
        if ($tenant === null) {
            return;
        }

        $approvers = $tenant->users()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['owner', 'pharmacist']))
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::send($approvers, new CashSessionCloseRequestedMail($session));
    }
}
