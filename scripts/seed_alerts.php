<?php

use App\Models\Alert;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenant = Tenant::query()->first();
if (! $tenant) {
    echo "no-tenant\n";
    exit(0);
}

app()->instance('current_tenant_id', (string) $tenant->id);

if (Alert::query()->count() > 0) {
    echo 'skip:'.Alert::query()->count()."\n";
    exit(0);
}

Alert::query()->create([
    'tenant_id' => $tenant->id,
    'type' => 'stock_critical',
    'severity' => 'critical',
    'title' => 'Stock critique — Ibuprofène 400mg',
    'body' => 'La quantité disponible est sous le seuil critique.',
    'status' => 'open',
    'raised_at' => now(),
]);

Alert::query()->create([
    'tenant_id' => $tenant->id,
    'type' => 'expiry_soon',
    'severity' => 'warning',
    'title' => 'Expiration proche — Ibuprofène 400mg',
    'body' => 'Le lot LOT-IBU-01 expire dans moins de 30 jours.',
    'status' => 'open',
    'raised_at' => now()->subHour(),
]);

echo "alerts-ok\n";
