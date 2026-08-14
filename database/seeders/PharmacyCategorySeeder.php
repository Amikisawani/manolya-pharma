<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PharmacyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = config('pharmacy_categories.names', []);

        Tenant::query()->each(function (Tenant $tenant) use ($names): void {
            app()->instance('current_tenant_id', (string) $tenant->id);

            foreach ($names as $name) {
                Category::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                    ],
                );
            }

            // Alias courts déjà présents en démo → pointer vers le libellé long si besoin
            $aliases = [
                'Antalgiques' => 'Antalgiques (contre la douleur)',
            ];

            foreach ($aliases as $short => $full) {
                $shortCat = Category::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $short)
                    ->first();
                $fullCat = Category::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $full)
                    ->first();

                if ($shortCat && $fullCat && $shortCat->id !== $fullCat->id) {
                    $shortCat->products()->update(['category_id' => $fullCat->id]);
                }
            }
        });
    }
}
