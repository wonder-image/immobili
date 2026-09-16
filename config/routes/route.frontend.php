<?php

use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Catalog\ListingRoute;

Route::area('frontend')
    ->response('html')
    ->group(function () {

        Route::name('immobili.')
            ->prefix('/immobili')
            ->group(function () {

                foreach (ListingRoute::PROPERTIES as $name => $preset) {
                    Route::get($preset['path'], Immobili::viewPath('pages/frontend/immobili/list.php'))
                        ->name(substr($name, strlen('immobili.')));
                }

                // Feed XML per il portale Idealista (crawler). Dichiarato prima di
                Route::get('/idealista/', Immobili::httpPath('frontend/idealista.php'))
                    ->name('idealista');

            });

        Route::name('residenze.')
            ->prefix('/residenze')
            ->group(function () {

                foreach (ListingRoute::RESIDENCES as $name => $preset) {
                    Route::get($preset['path'], Immobili::viewPath('pages/frontend/residenze/list.php'))
                        ->name(substr($name, strlen('residenze.')));
                }
                Route::get('/completate/', Immobili::viewPath('pages/frontend/residenze/list.php'), [
                    'catalog_alias' => 'residenze.realizzate',
                ])->name('completate');

            });

        Route::name('residenza.')
            ->prefix('/residenza')
            ->group(function () {

                Route::get('/{slug}/', Immobili::viewPath('pages/frontend/residenze/detail.php'))
                    ->name('view');

            });

        Route::name('immobile.')
            ->prefix('/immobile/{slug}')
            ->group(function () {

                // Dettaglio immobile per slug (deve restare l'ultima del gruppo).
                Route::get('/', Immobili::viewPath('pages/frontend/immobili/detail.php'))
                    ->name('view');

                Route::get('/scheda-immobile/', Immobili::httpPath('frontend/immobile/pdf/scheda.php'))
                    ->name('scheda');

                Route::get('/cartello/', Immobili::httpPath('frontend/immobile/pdf/cartello.php'))
                    ->name('cartello');

                Route::get('/cartello-vetrina/', Immobili::httpPath('frontend/immobile/pdf/cartello-vetrina.php'))
                    ->name('cartello.vetrina');

                Route::get(
                    '/cartello-vetrina-venduto/',
                    Immobili::httpPath('frontend/immobile/pdf/cartello-vetrina.php'),
                    ['sold' => true]
                )
                    ->name('cartello.vetrina.venduto');

            });
            

    });
