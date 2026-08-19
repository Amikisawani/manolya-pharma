<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ManolyaBootstrap;
use Illuminate\Console\Command;

class ManolyaBootstrapCommand extends Command
{
    protected $signature = 'manolya:bootstrap {--force : Créer même si des utilisateurs existent déjà (ignoré — no-op)}';

    protected $description = 'Assure rôles Spatie, super admin /admin et owner pharmacie /login';

    public function handle(ManolyaBootstrap $bootstrap): int
    {
        if (! $bootstrap->needsSetup()) {
            $bootstrap->ensureRoles();
            $owner = $bootstrap->ensurePharmacyOwner();
            $this->info('Manolya déjà initialisée (super admin présent). Rôles synchronisés.');
            if ($owner) {
                $this->info('Compte pharmacie recréé : '.$owner->email.' → /login');
            }

            return self::SUCCESS;
        }

        $user = $bootstrap->bootstrapFromConfigIfEmpty();

        if (! $user) {
            $this->warn('Base vide : crée le owner via /setup ou définis SETUP_OWNER_* sur Render.');

            return self::SUCCESS;
        }

        $this->info('Super admin créé : '.$user->email.' → /admin/login');
        $pharmacyOwner = $bootstrap->ensurePharmacyOwner()
            ?? User::query()
                ->whereNotNull('tenant_id')
                ->whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                ->first();
        if ($pharmacyOwner) {
            $this->info('Compte pharmacie : '.$pharmacyOwner->email.' → /login');
        }

        return self::SUCCESS;
    }
}
