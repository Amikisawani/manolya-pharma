<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Services\ProductCatalogSpreadsheet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportCatalogCommand extends Command
{
    protected $signature = 'catalog:import
        {path : Chemin absolu du fichier .xlsx ou .csv}
        {tenant : UUID du tenant}
        {format=xlsx : xlsx, csv ou txt}
        {--user= : UUID utilisateur (mouvements de stock)}
        {--delete : Supprimer le fichier après import}';

    protected $description = 'Importe un catalogue Excel/CSV hors requête HTTP (évite les 502 Render)';

    public function handle(ProductCatalogSpreadsheet $spreadsheet): int
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(180);

        $path = (string) $this->argument('path');
        $tenantId = (string) $this->argument('tenant');
        $format = strtolower((string) $this->argument('format'));
        $userId = $this->option('user');
        $userId = is_string($userId) && $userId !== '' ? $userId : null;

        app()->instance('current_tenant_id', $tenantId);

        try {
            $result = $spreadsheet->importFromFile($path, $tenantId, $format, $userId);
            Cache::put('catalog-import:'.$tenantId, $result, now()->addHour());
            Log::info('catalog.import.finished', ['tenant_id' => $tenantId] + $result);
            $this->info(ProductCatalogSpreadsheet::resultMessage($result));

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $failed = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['Impossible de lire le fichier. Utilisez un .xlsx ou .csv (séparateur ;) généré depuis le modèle Manolya.'],
            ];
            Cache::put('catalog-import:'.$tenantId, $failed, now()->addHour());
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($this->option('delete') && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
