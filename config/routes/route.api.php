<?php

use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Immobili;

Route::area('api')
    ->response('json')
    ->group(function () {
        Route::name('api.immobili.')
            ->prefix('/immobili')
            ->group(function () {

                // Sincronizzazione feed. Con parametro `feed` (id) sincronizza
                // solo quella FeedSource, altrimenti tutti i feed attivi. Pensata
                // per il cron lato hosting e per il push di Gestim (che invia
                // `callback` in querystring). Vedi http/api/task/sync.php.
                Route::get('/sync/', Immobili::httpPath('api/task/sync.php'))
                    ->name('sync');

                // Secondo piano immagini: scarica gli originali a massima
                // risoluzione e genera le varianti responsive (webp) a lotti.
                // Da agganciare a un cron separato dalla sync.
                Route::get('/images/', Immobili::httpPath('api/task/images.php'))
                    ->name('images');

                // Seed di immobili di esempio (solo ambiente locale) per verifica.
                Route::get('/seed/', Immobili::httpPath('api/task/seed.php'))
                    ->name('seed');

                // Seed di residenze di esempio (solo ambiente locale) per verifica.
                Route::get('/residenze-seed/', Immobili::httpPath('api/task/residenze-seed.php'))
                    ->name('residenze_seed');

                // Backfill idempotente dei campi derivati di ricerca
                // (comune_nome/tipologia_nome/ricerca) sugli immobili esistenti.
                Route::get('/reindex/', Immobili::httpPath('api/task/reindex.php'))
                    ->name('reindex');

                // Ricerca/paginazione degli immobili per la lista frontend (JSON).
                Route::get('/search/', Immobili::httpPath('api/frontend/search.php'))
                    ->name('search');

            });
    });
