<?php

use App\Models\Site;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$updated = Site::query()
    ->where('name', 'Site Centre-ville')
    ->orWhere('code', 'KIN-01')
    ->orWhere('code', 'BZV-01')
    ->update([
        'name' => 'Site Bandal',
        'code' => 'KIN-BANDAL',
        'address' => 'Bandalungwa, Kinshasa, RDC',
    ]);

echo "sites-updated:{$updated}\n";
