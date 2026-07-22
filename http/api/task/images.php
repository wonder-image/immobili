<?php

use Wonder\Api\Endpoint;
use Wonder\Api\Handler;
use Wonder\Plugin\Immobili\Services\ImageProcessor;

/**
 * Secondo piano della pipeline immagini.
 *
 *   GET /api/immobili/images/            → elabora un lotto (default 30)
 *   GET /api/immobili/images/?limit=50   → dimensione del lotto
 *
 * Scarica gli originali a massima risoluzione e genera le varianti responsive
 * (webp + formati di default). Da agganciare a un cron distinto da quello di
 * sincronizzazione. Autenticazione: token dell'utente API dedicato `@immobili`
 * (`Authorization: Bearer <token>`), come per l'endpoint di sync.
 */

require __DIR__.'/_bearer.php';

Handler::run('/api/immobili/images/', 'GET', ['immobili_sync', 'api_internal_user'], function (Endpoint $call) {
    $limit = (int) ($call->parameters['limit'] ?? 30);
    $result = (new ImageProcessor())->process($limit);

    return [
        'success'  => true,
        'status'   => 200,
        'response' => $result,
    ];
});
