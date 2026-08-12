<?php

use App\Jobs\DetectStockThresholdsJob;
use App\Jobs\GenerateDailyReportJob;
use App\Jobs\GenerateMonthlyReportJob;
use App\Jobs\ScanBatchExpiriesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tz = 'Africa/Kinshasa';

Schedule::job(new ScanBatchExpiriesJob)->dailyAt('01:00')->timezone($tz);
Schedule::job(new DetectStockThresholdsJob)->hourly()->timezone($tz);
Schedule::job(new GenerateDailyReportJob)->dailyAt('23:59')->timezone($tz);
Schedule::job(new GenerateMonthlyReportJob)->monthlyOn(1, '00:15')->timezone($tz);
