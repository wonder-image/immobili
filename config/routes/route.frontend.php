<?php

use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Immobili;

Route::area('frontend')
    ->response('html')
    ->group(function () {

        Route::name('immobili.')
            ->prefix('/immobili')
            ->group(function () {

                // Lista immobili in vendita/affitto (griglia + mappa + filtri).
                Route::get('/', Immobili::viewPath('pages/frontend/list.php'))
                    ->name('list');

                // Immobili venduti (portfolio).
                Route::get('/venduti/', Immobili::viewPath('pages/frontend/sold.php'))
                    ->name('sold');

                // Feed XML per il portale Idealista (crawler). Dichiarato prima di
                // /{slug}/ per non essere interpretato come slug.
                Route::get('/idealista/', Immobili::httpPath('frontend/idealista.php'))
                    ->name('idealista');

                // Dettaglio immobile per slug (deve restare l'ultima del gruppo).
                Route::get('/{slug}/', Immobili::viewPath('pages/frontend/detail.php'))
                    ->name('detail');

            });

        Route::name('immobile.')
            ->prefix('/immobile/{slug}')
            ->group(function () {

                // Dettaglio immobile per slug (deve restare l'ultima del gruppo).
                Route::get('/', Immobili::viewPath('pages/frontend/detail.php'))
                    ->name('view');

                Route::get('/scheda-immobile/', Immobili::httpPath('frontend/immobile/pdf/scheda.php'))
                    ->name('scheda');

                Route::get('/cartello/', Immobili::httpPath('frontend/immobile/pdf/cartello.php'))
                    ->name('cartello');

                Route::get('/cartello-vetrina/', Immobili::httpPath('frontend/immobile/pdf/cartello-vetrina.php'))
                    ->name('cartello.vetrina');

                Route::get('/cartello-vetrina-venduto/', Immobili::httpPath('frontend/immobile/pdf/cartello-vetrina.php?sold=1'))
                    ->name('cartello.vetrina-venduto');

            });
            

    });
