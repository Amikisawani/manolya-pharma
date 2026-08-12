<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Batch;
use App\Models\Tenant;
use App\Notifications\CriticalAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class ScanBatchExpiriesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant): void {
            app()->instance('current_tenant_id', (string) $tenant->id);

            Batch::query()
                ->where('expires_at', '<', now()->toDateString())
                ->where('quantity_on_hand', '>', 0)
                ->where('status', '!=', Batch::STATUS_EXPIRED)
                ->each(function (Batch $batch): void {
                    $batch->update(['status' => Batch::STATUS_EXPIRED]);

                    $alert = Alert::query()->create([
                        'tenant_id' => $batch->tenant_id,
                        'type' => 'batch_expired',
                        'severity' => 'critical',
                        'title' => 'Lot expiré',
                        'body' => "Le lot {$batch->lot_number} est expiré.",
                        'reference_type' => Batch::class,
                        'reference_id' => $batch->id,
                        'status' => 'open',
                        'raised_at' => now(),
                    ]);

                    $owners = $tenant->users()->role('owner')->get();
                    if ($owners->isNotEmpty()) {
                        Notification::send($owners, new CriticalAlertNotification($alert));
                    }
                });

            Batch::query()
                ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->where('quantity_on_hand', '>', 0)
                ->where('status', Batch::STATUS_ACTIVE)
                ->each(function (Batch $batch) use ($tenant): void {
                    $exists = Alert::query()
                        ->where('type', 'batch_expiring')
                        ->where('reference_id', $batch->id)
                        ->where('status', 'open')
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    Alert::query()->create([
                        'tenant_id' => $tenant->id,
                        'type' => 'batch_expiring',
                        'severity' => 'warning',
                        'title' => 'Lot bientôt périmé',
                        'body' => "Le lot {$batch->lot_number} expire le {$batch->expires_at?->format('d/m/Y')}.",
                        'reference_type' => Batch::class,
                        'reference_id' => $batch->id,
                        'status' => 'open',
                        'raised_at' => now(),
                    ]);
                });
        });
    }
}
