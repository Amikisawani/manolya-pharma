<?php

namespace App\Domain\Reporting\Services;

use App\Models\ReportRun;
use App\Models\Tenant;
use App\Notifications\OwnerDailyReportMail;
use App\Notifications\OwnerMonthlyReportMail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

final class OwnerReportDispatcher
{
    public function __construct(
        private readonly OwnerReportBuilder $builder,
        private readonly OwnerReportPdfGenerator $pdf,
    ) {}

    public function dispatchDaily(Tenant $tenant, ?CarbonInterface $day = null, bool $send = true): ReportRun
    {
        $tz = $tenant->timezone ?: 'Africa/Kinshasa';
        $day = ($day ?? Carbon::now($tz))->copy()->timezone($tz)->startOfDay();

        app()->instance('current_tenant_id', (string) $tenant->id);

        $payload = $this->builder->daily($tenant, $day);
        $jsonPath = "reports/{$tenant->id}/daily-{$day->toDateString()}.json";
        $pdfPath = "reports/{$tenant->id}/daily-{$day->toDateString()}.pdf";

        Storage::disk('local')->put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->pdf->store($payload, $pdfPath);

        $run = ReportRun::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'type' => 'daily',
                'period_start' => $day->toDateString(),
                'period_end' => $day->toDateString(),
            ],
            [
                'disk_path' => $pdfPath,
                'status' => 'ready',
                'sent_at' => null,
            ],
        );

        if ($send) {
            $this->notifyOwners($tenant, new OwnerDailyReportMail($run, $payload, $pdfPath));
            $run->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return $run->fresh();
    }

    public function dispatchMonthly(Tenant $tenant, ?CarbonInterface $month = null, bool $send = true): ReportRun
    {
        $tz = $tenant->timezone ?: 'Africa/Kinshasa';
        $month = ($month ?? Carbon::now($tz)->subMonthNoOverflow())->copy()->timezone($tz);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        app()->instance('current_tenant_id', (string) $tenant->id);

        $payload = $this->builder->monthly($tenant, $start, $end);
        $jsonPath = "reports/{$tenant->id}/monthly-{$start->format('Y-m')}.json";
        $pdfPath = "reports/{$tenant->id}/monthly-{$start->format('Y-m')}.pdf";

        Storage::disk('local')->put($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->pdf->store($payload, $pdfPath);

        $run = ReportRun::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'type' => 'monthly',
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'disk_path' => $pdfPath,
                'status' => 'ready',
                'sent_at' => null,
            ],
        );

        if ($send) {
            $this->notifyOwners($tenant, new OwnerMonthlyReportMail($run, $payload, $pdfPath));
            $run->update(['status' => 'sent', 'sent_at' => now()]);
        }

        return $run->fresh();
    }

    private function notifyOwners(Tenant $tenant, object $notification): void
    {
        $owners = $tenant->users()->role('owner')->where('is_active', true)->get();
        if ($owners->isEmpty()) {
            return;
        }

        Notification::send($owners, $notification);
    }
}
