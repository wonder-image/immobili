<?php

use Wonder\Plugin\Immobili\Sync\ReindexService;

/**
 * Backfill idempotente dei campi derivati per la ricerca SQL della lista.
 *
 *   GET /api/immobili/reindex/   → ricalcola comune_nome/tipologia_nome e fa il
 *                                  backfill dello slug per tutti gli immobili non
 *                                  cancellati che ne sono privi
 *
 * Disponibile senza credenziali solo in ambiente locale; fuori dal locale
 * richiede il token dell'utente API `@immobili`. Vedi `_guard.php`.
 * La logica vive in `Wonder\Plugin\Immobili\Sync\ReindexService`.
 */

require __DIR__.'/_guard.php';

immobiliTaskGuard('Reindex');

echo json_encode([
    'success'  => true,
    'status'   => 200,
    'response' => (new ReindexService())->run(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
