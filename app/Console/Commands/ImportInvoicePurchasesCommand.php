<?php

namespace App\Console\Commands;

use App\Domain\Purchasing\Services\InvoicePurchaseImporter;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ImportInvoicePurchasesCommand extends Command
{
    protected $signature = 'manolya:import-invoice-purchases
        {--tenant= : Tenant UUID or slug}
        {--file= : Chemin du JSON des lignes de facture}
        {--warehouse= : Code ou UUID du dépôt}
        {--markup=1.4 : Coefficient prix de vente / prix d’achat}
        {--expires= : Date de péremption placeholder (YYYY-MM-DD)}
        {--purchased-at= : Date d’achat (YYYY-MM-DD)}
        {--dry-run : Compte sans écrire}';

    protected $description = 'Importe les factures d’achat (catalogue + lots vendables) depuis le JSON extrait';

    public function handle(InvoicePurchaseImporter $importer): int
    {
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

        if ($tenants->count() > 1 && ! $this->option('tenant')) {
            $this->error('Plusieurs tenants actifs. Précisez --tenant=slug.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('file') ?: InvoicePurchaseImporter::defaultDatasetPath());

        try {
            $stats = $importer->import($path, $tenants->first(), [
                'markup' => $this->option('markup'),
                'expires_at' => $this->option('expires'),
                'purchased_at' => $this->option('purchased-at'),
                'dry_run' => (bool) $this->option('dry-run'),
                'warehouse_id' => $this->option('warehouse'),
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('Mode dry-run : aucune écriture.');
        }

        $this->table(
            ['Produits créés', 'Produits réutilisés', 'Lots créés', 'Lots déjà présents', 'Lignes ignorées', 'Fournisseurs créés'],
            [[
                $stats['products_created'],
                $stats['products_reused'],
                $stats['batches_created'],
                $stats['batches_skipped'],
                $stats['lines_skipped'],
                $stats['suppliers_created'],
            ]],
        );

        foreach ($stats['warnings'] as $warning) {
            $this->warn($warning);
        }

        foreach ($stats['errors'] as $error) {
            $this->error($error);
        }

        return $stats['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
