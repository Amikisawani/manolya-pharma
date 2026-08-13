<?php

return [
    /*
    | Devise d'affichage principale — Franc congolais (CDF / Fc).
    | Les taux sont indicatifs et configurables (mise à jour manuelle ou API plus tard).
    */
    'default' => env('CURRENCY_DEFAULT', 'CDF'),
    'symbol' => env('CURRENCY_SYMBOL', 'Fc'),

    'rates' => [
        // Combien de CDF pour 1 unité de devise étrangère
        'USD' => (float) env('FX_USD_CDF', 2350),
        'EUR' => (float) env('FX_EUR_CDF', 2702.5),
    ],
];
