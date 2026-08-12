<?php

namespace App\Jobs;

use App\Domain\Reporting\Services\OwnerReportDispatcher;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMonthlyReportJob implements ShouldQueue
{
    use Queueable;

    public function handle(OwnerReportDispatcher $dispatcher): void
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($dispatcher): void {
            $tz = $tenant->timezone ?: 'Africa/Kinshasa';
            $month = Carbon::now($tz)->subMonthNoOverflow();

            $dispatcher->dispatchMonthly($tenant, $month, send: true);
        });
    }
}
