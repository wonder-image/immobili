<?php

return [
    'backend' => [
        'immobili_manager' => [
            'name' => 'Immobili',
            'icon' => "<i class='bi bi-house-door'></i>",
            'bg' => 'bg-primary',
            'tx' => 'text-white',
            'color' => 'primary',
            'creator' => ['admin'],
        ],
    ],

    // Authority (area API) dell'utente dedicato alla sincronizzazione dei feed.
    // È l'identità con cui i cron/Gestim autenticano le chiamate a
    // /api/immobili/{sync,images,seed}/: il token JWT dell'utente `@immobili`
    // vive in `api_users` (vedi SyncApiUser) ed è l'unico segreto necessario
    // (nessuna variabile d'ambiente). L'area `api` fornisce già le funzioni
    // creation/modify/info (apiUser/infoApiUser), quindi il token viene
    // generato e risolto come per l'utente di sistema `@system`.
    'api' => [
        'immobili_sync' => [
            'name' => 'Immobili Sync',
            'icon' => "<i class='bi bi-arrow-repeat'></i>",
            'bg' => 'bg-dark',
            'tx' => 'text-white',
            'color' => 'dark',
            'creator' => ['admin'],
        ],
    ],
];
