<?php

use Wonder\Plugin\Immobili\Services\ImmobileSeeder;
use Wonder\Plugin\Immobili\Services\SyncApiUser;

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

header('Content-Type: application/json; charset=utf-8');

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocal = getenv('APP_ENV') === 'local'
    || (bool) preg_match('/(^localhost|127\.0\.0\.1|\.test$|\.local$|\.localhost$|\.ddev\.site$)/', $host);

// Token presentato: header Bearer oppure ?token= (fallback).
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader && function_exists('getallheaders')) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

$presented = (is_string($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $m))
    ? $m[1]
    : trim((string) ($_GET['token'] ?? ''));

if (!$isLocal && !SyncApiUser::authorize($presented)) {
    http_response_code(403);
    echo json_encode([
        'success'  => false,
        'status'   => 403,
        'response' => ['message' => 'Seed disponibile solo in ambiente locale o con token API valido.'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$count = (int) ($_GET['count'] ?? 12);
$created = (new ImmobileSeeder())->seed($count);

echo json_encode([
    'success'  => true,
    'status'   => 200,
    'response' => ['created' => $created],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
