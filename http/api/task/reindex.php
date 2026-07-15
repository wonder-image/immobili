<?php

use Wonder\Api\Endpoint;
use Wonder\Api\Handler;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

/**
 * Backfill idempotente dei campi derivati per la ricerca SQL della lista.
 *
 *   GET /api/immobili/reindex/   → ricalcola comune_nome/tipologia_nome/ricerca
 *                                  per tutti gli immobili non cancellati
 *
 * Serve a popolare i campi denormalizzati sui record importati prima
 * dell'introduzione delle colonne (dopo il sync questi valori sono già
 * mantenuti aggiornati). Sicuro da rieseguire. Autenticazione: token
 * dell'utente API dedicato `@immobili` (`Authorization: Bearer <token>`),
 * come per sync e images.
 */

require __DIR__.'/_bearer.php';

Handler::run('/api/immobili/reindex/', 'GET', ['immobili_sync', 'api_internal_user'], function (Endpoint $call) {
    $presenter = new ImmobilePresenter();

    $rows = Immobile::find(['deleted' => 'false']);
    $rows = is_array($rows) ? (isset($rows['id']) ? [$rows] : array_values($rows)) : [];

    $updated = 0;
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        Immobile::update($presenter->searchFields($row), $id);
        $updated++;
    }

    return [
        'success'  => true,
        'status'   => 200,
        'response' => ['updated' => $updated],
    ];
});
