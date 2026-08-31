<?php

use Wonder\Api\Endpoint;
use Wonder\Api\Handler;
use Wonder\Plugin\Immobili\Sync\FeedSyncService;

/**
 * Endpoint di sincronizzazione feed.
 *
 *   GET /api/immobili/sync/                          → sincronizza tutti i feed attivi
 *   GET /api/immobili/sync/?feed=<id>                → sincronizza solo quel feed
 *   GET /api/immobili/sync/?feed=<id>&callback=<zip> → push Gestim
 *
 * Autenticazione: token dell'utente API dedicato `@immobili` (authority
 * `immobili_sync`, area `api`). Gli scheduler HTTP lo inviano come
 * `Authorization: Bearer <token>`; Gestim lo appende come `?token=<token>`,
 * trasformato in Bearer dal ponte incluso qui sotto. La validazione (firma JWT,
 * utente, authority, IP/dominio) è a carico di Wonder\Api\Endpoint.
 */

require __DIR__.'/_bearer.php';

Handler::run('/api/immobili/sync/', 'GET', ['immobili_sync', 'api_internal_user'], function (Endpoint $call) {
    $service = new FeedSyncService();
    $feed = trim((string) ($call->parameters['feed'] ?? ''));

    $response = $feed !== ''
        ? [$service->syncById($feed)]
        : $service->syncAll();

    $success = $response !== [];
    foreach ($response as $result) {
        if (empty($result['success'])) {
            $success = false;
            break;
        }
    }

    return [
        'success'  => $success,
        'status'   => $success ? 200 : 422,
        'response' => $response,
    ];
});
