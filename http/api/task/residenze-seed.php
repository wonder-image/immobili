<?php

use Wonder\Plugin\Immobili\Seeding\ResidenzaSeeder;

/**
 * Seed di residenze di esempio per la verifica locale (frontend + backend).
 *
 *   GET /api/immobili/residenze-seed/   → rigenera 5 residenze di esempio
 *
 * Disponibile senza credenziali solo in ambiente locale
 * (host localhost/.test/.local/…). Fuori dal locale richiede il token
 * dell'utente API `@immobili` (header `Authorization: Bearer <token>` o
 * `?token=<token>`). Rigenera il set (rimuove i seed precedenti, code
 * `seedres-*`) e collega alcuni immobili visibili via `residenza_id`.
 */

require __DIR__.'/_guard.php';

immobiliTaskGuard('Seed');

$created = (new ResidenzaSeeder())->seed();

echo json_encode([
    'success'  => true,
    'status'   => 200,
    'response' => ['created' => $created],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
