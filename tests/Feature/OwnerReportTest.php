<?php

namespace Tests\Feature;

use App\Domain\Reporting\Services\OwnerReportDispatcher;
use App\Models\ReportRun;
use App\Models\User;
use App\Notifications\OwnerDailyReportMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_creates_pdf_and_notifies_owner(): void
    {
        $this->seed();
        Storage::fake('local');
        Notification::fake();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $tenant = $owner->tenant;

        $run = app(OwnerReportDispatcher::class)->dispatchDaily($tenant, now($tenant->timezone), send: true);

        $this->assertSame('daily', $run->type);
        $this->assertSame('sent', $run->status);
        $this->assertNotNull($run->disk_path);
        Storage::disk('local')->assertExists($run->disk_path);
        $this->assertStringEndsWith('.pdf', $run->disk_path);

        Notification::assertSentTo($owner, OwnerDailyReportMail::class);
    }

    public function test_owner_can_generate_daily_report_from_finance(): void
    {
        $this->seed();
        Storage::fake('local');
        Notification::fake();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();

        $this->actingAs($owner)
            ->post(route('finance.reports.daily'), ['send' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('report_runs', [
            'tenant_id' => $owner->tenant_id,
            'type' => 'daily',
            'status' => 'sent',
        ]);

        $run = ReportRun::query()->where('type', 'daily')->firstOrFail();
        Storage::disk('local')->assertExists($run->disk_path);
    }

    public function test_monthly_report_pdf_is_stored(): void
    {
        $this->seed();
        Storage::fake('local');
        Notification::fake();

        $owner = User::query()->where('email', 'owner@manolya.test')->firstOrFail();
        $run = app(OwnerReportDispatcher::class)->dispatchMonthly($owner->tenant, now()->subMonth(), send: false);

        $this->assertSame('monthly', $run->type);
        $this->assertSame('ready', $run->status);
        Storage::disk('local')->assertExists($run->disk_path);
    }
}
