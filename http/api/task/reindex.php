<?php

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Catalog\ImmobilePresenter;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Backfill idempotente dei campi derivati per la ricerca SQL della lista.
 *
 *   GET /api/immobili/reindex/   → ricalcola comune_nome/tipologia_nome e fa il
 *                                  backfill dello slug per tutti gli immobili non
 *                                  cancellati che ne sono privi
 *
 * Serve a popolare i campi denormalizzati sui record importati prima
 * dell'introduzione delle colonne (dopo il sync questi valori sono già
 * mantenuti aggiornati). Sicuro da rieseguire.
 *
 * Disponibile senza credenziali solo in ambiente locale
 * (host localhost/.test/.local/…), come il seed. Fuori dal locale richiede il
 * token dell'utente API `@immobili` (header `Authorization: Bearer <token>` o
 * `?token=<token>`).
 */

require __DIR__.'/_guard.php';

immobiliTaskGuard('Reindex');

$presenter = new ImmobilePresenter();

$rows = Immobile::find(['deleted' => 'false']);
$rows = is_array($rows) ? (isset($rows['id']) ? [$rows] : array_values($rows)) : [];

$updated = 0;
foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);

    if ($id <= 0) {
        continue;
    }

    $fields = $presenter->searchFields($row);

    // Backfill dello slug per i record che ne sono privi (es. dopo la migrazione
    // dir→slug): base leggibile resa univoca, escludendo il record stesso.
    if (trim((string) ($row['slug'] ?? '')) === '') {
        $base = Slug::base([
            $fields['tipologia_nome'] ?? '',
            $row['strada'] ?? '',
            $row['indirizzo'] ?? '',
            $fields['comune_nome'] ?? '',
        ]);
        $fields['slug'] = Slug::unique($base, Immobile::class, $id);
    }

    Immobile::update($fields, $id);
    $updated++;
}

echo json_encode([
    'success'  => true,
    'status'   => 200,
    'response' => ['updated' => $updated],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
