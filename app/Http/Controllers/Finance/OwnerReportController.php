<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Reporting\Services\OwnerReportDispatcher;
use App\Http\Controllers\Controller;
use App\Models\ReportRun;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerReportController extends Controller
{
    public function download(Request $request, ReportRun $report): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.reports.view') || $request->user()?->can('reports.daily.view'), 403);
        abort_unless(
            $request->user()?->isSuperAdmin() || $request->user()?->tenant_id === $report->tenant_id,
            403,
        );
        abort_unless($report->disk_path && Storage::disk('local')->exists($report->disk_path), 404);

        $filename = ($report->type === 'monthly' ? 'rapport-mensuel-' : 'rapport-quotidien-')
            .$report->period_start->format('Y-m-d')
            .'.pdf';

        return Storage::disk('local')->download($report->disk_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function generateDaily(Request $request, OwnerReportDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($request->user()?->can('reports.daily.view') || $request->user()?->can('finance.reports.view'), 403);

        $tenant = $request->user()->tenant;
        abort_unless($tenant, 422);

        $day = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString(), $tenant->timezone ?: 'Africa/Kinshasa')
            : Carbon::now($tenant->timezone ?: 'Africa/Kinshasa');

        $send = $request->boolean('send', true);
        $run = $dispatcher->dispatchDaily($tenant, $day, send: $send);

        return back()->with('success', 'Rapport quotidien généré'.($send ? ' et envoyé.' : '.')." ({$run->period_start->toDateString()})");
    }

    public function generateMonthly(Request $request, OwnerReportDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($request->user()?->can('reports.monthly.view') || $request->user()?->can('finance.reports.view'), 403);

        $tenant = $request->user()->tenant;
        abort_unless($tenant, 422);

        $month = $request->filled('month')
            ? Carbon::parse($request->string('month')->toString().'-01', $tenant->timezone ?: 'Africa/Kinshasa')
            : Carbon::now($tenant->timezone ?: 'Africa/Kinshasa')->subMonthNoOverflow();

        $send = $request->boolean('send', true);
        $run = $dispatcher->dispatchMonthly($tenant, $month, send: $send);

        return back()->with('success', 'Rapport mensuel généré'.($send ? ' et envoyé.' : '.')." ({$run->period_start->format('Y-m')})");
    }
}
