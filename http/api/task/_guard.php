<?php

use Wonder\Plugin\Immobili\Sync\SyncApiUser;

/**
 * Gate condiviso dei task amministrativi del modulo (seed, reindex).
 *
 * In ambiente locale passano senza credenziali, così da poterli lanciare dal
 * browser durante lo sviluppo. Fuori dal locale richiedono il token
 * dell'utente API dedicato `@immobili`, presentato come
 * `Authorization: Bearer <token>` oppure come `?token=<token>` (fallback per i
 * client che non possono impostare header).
 *
 * Da non confondere con `_bearer.php`, che non autorizza: si limita a
 * sintetizzare l'header Authorization da `?token=` per gli endpoint che
 * passano da `Wonder\Api\Endpoint`.
 */

if (!function_exists('immobiliTaskIsLocal')) {
    function immobiliTaskIsLocal(): bool
    {
        if (getenv('APP_ENV') === 'local') {
            return true;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        return (bool) preg_match(
            '/(^localhost|127\.0\.0\.1|\.test$|\.local$|\.localhost$|\.ddev\.site$)/',
            $host
        );
    }
}

if (!function_exists('immobiliTaskPresentedToken')) {
    function immobiliTaskPresentedToken(): string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (is_string($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
            return $m[1];
        }

        return trim((string) ($_GET['token'] ?? ''));
    }
}

if (!function_exists('immobiliTaskGuard')) {
    /**
     * Interrompe la richiesta con 403 se non siamo in locale e il token
     * presentato non è valido. Imposta anche il Content-Type JSON della
     * risposta, comune a tutti i task.
     */
    function immobiliTaskGuard(string $label): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (immobiliTaskIsLocal()) {
            return;
        }

        if (SyncApiUser::authorize(immobiliTaskPresentedToken())) {
            return;
        }

        http_response_code(403);
        echo json_encode([
            'success'  => false,
            'status'   => 403,
            'response' => ['message' => $label.' disponibile solo in ambiente locale o con token API valido.'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
