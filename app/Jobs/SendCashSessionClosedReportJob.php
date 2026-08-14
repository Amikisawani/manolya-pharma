<?php

namespace App\Jobs;

use App\Domain\Reporting\Services\CashSessionClosePdfGenerator;
use App\Domain\Reporting\Services\CashSessionCloseReportBuilder;
use App\Models\CashRegisterSession;
use App\Notifications\CashSessionClosedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class SendCashSessionClosedReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sessionId,
    ) {}

    public function handle(
        CashSessionCloseReportBuilder $builder,
        CashSessionClosePdfGenerator $pdf,
    ): void {
        $session = CashRegisterSession::query()
            ->with(['tenant', 'opener', 'closer', 'site', 'warehouse'])
            ->find($this->sessionId);

        if ($session === null || $session->status !== CashRegisterSession::STATUS_CLOSED) {
            return;
        }

        app()->instance('current_tenant_id', (string) $session->tenant_id);

        $payload = $builder->build($session);
        $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $session->number) ?: $session->id;
        $pdfPath = "reports/{$session->tenant_id}/cash-sessions/{$safeNumber}.pdf";
        $jsonPath = "reports/{$session->tenant_id}/cash-sessions/{$safeNumber}.json";

        Storage::disk('local')->put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $pdf->store($payload, $pdfPath);

        $tenant = $session->tenant;
        if ($tenant === null) {
            return;
        }

        $owners = $tenant->users()->role('owner')->where('is_active', true)->get();
        if ($owners->isEmpty()) {
            return;
        }

        Notification::send($owners, new CashSessionClosedMail($payload, $pdfPath));
    }
}
