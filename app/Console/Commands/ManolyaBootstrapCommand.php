<?php

namespace App\Console\Commands;

use App\Services\ManolyaBootstrap;
use Illuminate\Console\Command;

class ManolyaBootstrapCommand extends Command
{
    protected $signature = 'manolya:bootstrap {--force : Créer même si des utilisateurs existent déjà (ignoré — no-op)}';

    protected $description = 'Assure rôles Spatie + premier owner si base vide (appli vierge)';

    public function handle(ManolyaBootstrap $bootstrap): int
    {
        if (! $bootstrap->needsSetup()) {
            $bootstrap->ensureRoles();
            $this->info('Manolya déjà initialisée (utilisateurs présents). Rôles synchronisés.');

            return self::SUCCESS;
        }

        $user = $bootstrap->bootstrapFromConfigIfEmpty();

        if (! $user) {
            $this->warn('Base vide : crée le owner via /setup ou définis SETUP_OWNER_* sur Render.');

            return self::SUCCESS;
        }

        $this->info('Owner créé : '.$user->email);

        return self::SUCCESS;
    }
}
