<?php

namespace App\Jobs;

use App\Domain\Catalog\Services\ProductCatalogSpreadsheet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportCatalogJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public string $path,
        public string $tenantId,
        public string $format,
        public ?string $userId = null,
        public bool $deleteFile = true,
    ) {
        $this->onConnection('database');
        $this->onQueue('imports');
    }

    public function handle(ProductCatalogSpreadsheet $spreadsheet): void
    {
        @ini_set('memory_limit', '192M');
        app()->instance('current_tenant_id', $this->tenantId);

        try {
            $result = $spreadsheet->importFromFile(
                $this->path,
                $this->tenantId,
                $this->format,
                $this->userId,
            );
            Cache::put('catalog-import:'.$this->tenantId, $result, now()->addHour());
            Log::info('catalog.import.finished', ['tenant_id' => $this->tenantId] + $result);
        } finally {
            if ($this->deleteFile && is_file($this->path)) {
                @unlink($this->path);
            }
        }
    }

    public function failed(?Throwable $e): void
    {
        report($e);
        Cache::put('catalog-import:'.$this->tenantId, [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => ['L’import a échoué. Vérifiez le fichier .xlsx / .csv et réessayez.'],
        ], now()->addHour());
    }
}
