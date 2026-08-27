<?php

use Wonder\Plugin\Immobili\Seeding\ImmobileSeeder;

/**
 * Seed di immobili di esempio per la verifica locale.
 *
 *   GET /api/immobili/seed/            → crea 12 immobili di esempio
 *   GET /api/immobili/seed/?count=20   → numero di immobili
 *
 * Disponibile senza credenziali solo in ambiente locale
 * (host localhost/.test/.local/…). Fuori dal locale richiede il token
 * dell'utente API `@immobili` (header `Authorization: Bearer <token>` o
 * `?token=<token>`). Rigenera il set (rimuove i seed precedenti). Gli immobili
 * di seed (provider='seed') non vengono toccati dai feed reali.
 */

require __DIR__.'/_guard.php';

immobiliTaskGuard('Seed');

$count = (int) ($_GET['count'] ?? 12);
$created = (new ImmobileSeeder())->seed($count);

echo json_encode([
    'success'  => true,
    'status'   => 200,
    'response' => ['created' => $created],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
