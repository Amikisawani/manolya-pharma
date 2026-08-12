<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Tenant;
use App\Notifications\CriticalAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class DetectStockThresholdsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant): void {
            app()->instance('current_tenant_id', (string) $tenant->id);

            Product::query()->each(function (Product $product) use ($tenant): void {
                $qty = (string) Batch::query()
                    ->where('product_id', $product->id)
                    ->where('status', Batch::STATUS_ACTIVE)
                    ->sum('quantity_on_hand');

                $critical = (string) ($product->critical_stock ?? '0');
                $min = (string) ($product->min_stock ?? '0');

                if (bccomp($qty, '0', 3) <= 0) {
                    $this->raise($tenant, $product, 'stockout', 'critical', 'Rupture de stock', "{$product->commercial_name} est en rupture.");
                } elseif (bccomp($qty, $critical, 3) <= 0) {
                    $this->raise($tenant, $product, 'critical_stock', 'critical', 'Stock critique', "{$product->commercial_name} est sous le seuil critique ({$qty}).");
                } elseif (bccomp($qty, $min, 3) <= 0) {
                    $this->raise($tenant, $product, 'min_stock', 'warning', 'Stock bas', "{$product->commercial_name} est sous le stock minimum ({$qty}).");
                }
            });
        });
    }

    private function raise(Tenant $tenant, Product $product, string $type, string $severity, string $title, string $body): void
    {
        $exists = Alert::query()
            ->where('type', $type)
            ->where('reference_id', $product->id)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return;
        }

        $alert = Alert::query()->create([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'status' => 'open',
            'raised_at' => now(),
        ]);

        if ($severity === 'critical') {
            $owners = $tenant->users()->role('owner')->get();
            if ($owners->isNotEmpty()) {
                Notification::send($owners, new CriticalAlertNotification($alert));
            }
        }
    }
}
