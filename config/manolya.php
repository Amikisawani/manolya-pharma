<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super admin plateforme (appli vierge)
    |--------------------------------------------------------------------------
    |
    | Compte /admin/login — hors app pharmacie. Créé par manolya:bootstrap.
    |
    */

    'bootstrap' => [
        'owner_name' => env('SETUP_OWNER_NAME', env('SETUP_ADMIN_NAME', 'Ami Kisawani')),
        'owner_email' => env('SETUP_OWNER_EMAIL', env('SETUP_ADMIN_EMAIL', 'amikisawani71@gmail.com')),
        'owner_password' => env('SETUP_OWNER_PASSWORD', env('SETUP_ADMIN_PASSWORD', 'amikis150898')),
        'pharmacy_name' => env('SETUP_PHARMACY_NAME', 'Pharmacie Manolya'),
        'pharmacy_owner_name' => env('SETUP_PHARMACY_OWNER_NAME', env('SETUP_OWNER_NAME', 'Ami Kisawani')),
        'pharmacy_owner_email' => env('SETUP_PHARMACY_OWNER_EMAIL', 'owner@manolya-pharma.site'),
        'site_name' => env('SETUP_SITE_NAME', 'Site principal'),
        'site_code' => env('SETUP_SITE_CODE', 'SITE-01'),
    ],

];
