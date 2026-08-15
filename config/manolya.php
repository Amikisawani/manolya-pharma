<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Premier propriétaire (appli vierge)
    |--------------------------------------------------------------------------
    |
    | Utilisé par `php artisan manolya:bootstrap` quand aucun utilisateur n'existe.
    | Préférer les variables d'environnement sur Render (sans Shell).
    |
    */

    'bootstrap' => [
        'owner_name' => env('SETUP_OWNER_NAME', 'Ami Kisawani'),
        'owner_email' => env('SETUP_OWNER_EMAIL', 'amikisawani71@gmail.com'),
        'owner_password' => env('SETUP_OWNER_PASSWORD', 'amikis150898'),
        'pharmacy_name' => env('SETUP_PHARMACY_NAME', 'Pharmacie Manolya'),
        'site_name' => env('SETUP_SITE_NAME', 'Site principal'),
        'site_code' => env('SETUP_SITE_CODE', 'SITE-01'),
    ],

];
