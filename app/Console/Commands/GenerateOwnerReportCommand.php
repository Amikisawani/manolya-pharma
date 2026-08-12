<?php

namespace App\Console\Commands;

use App\Domain\Reporting\Services\OwnerReportDispatcher;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateOwnerReportCommand extends Command
{
    protected $signature = 'manolya:report
        {type : daily|monthly}
        {--tenant= : Tenant UUID or slug}
        {--date= : YYYY-MM-DD for daily, or YYYY-MM for monthly}
        {--send : Email owners}';

    protected $description = 'Génère un rapport propriétaire PDF (quotidien ou mensuel)';

    public function handle(OwnerReportDispatcher $dispatcher): int
    {
        $type = $this->argument('type');
        if (! in_array($type, ['daily', 'monthly'], true)) {
            $this->error('type doit être daily ou monthly');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->where('status', 'active')
            ->when($this->option('tenant'), function ($q, $id): void {
                if (preg_match('/^[0-9a-fA-F-]{36}$/', (string) $id)) {
                    $q->whereKey($id);
                } else {
                    $q->where('slug', $id);
                }
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('Aucun tenant trouvé.');

            return self::FAILURE;
        }

        $send = (bool) $this->option('send');

        foreach ($tenants as $tenant) {
            $tz = $tenant->timezone ?: 'Africa/Kinshasa';

            if ($type === 'daily') {
                $day = $this->option('date')
                    ? Carbon::parse($this->option('date'), $tz)
                    : Carbon::now($tz);
                $run = $dispatcher->dispatchDaily($tenant, $day, send: $send);
            } else {
                $month = $this->option('date')
                    ? Carbon::parse(strlen((string) $this->option('date')) === 7 ? $this->option('date').'-01' : $this->option('date'), $tz)
                    : Carbon::now($tz)->subMonthNoOverflow();
                $run = $dispatcher->dispatchMonthly($tenant, $month, send: $send);
            }

            $this->info("{$tenant->slug}: {$run->type} {$run->period_start->toDateString()} → {$run->disk_path} [{$run->status}]");
        }

        return self::SUCCESS;
    }
}
