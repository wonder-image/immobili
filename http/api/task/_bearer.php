<?php

/**
 * Ponte query→Bearer per gli endpoint di sincronizzazione immobili.
 *
 * L'autenticazione avviene con il token dell'utente API dedicato `@immobili`
 * (vedi Wonder\Plugin\Immobili\Sync\SyncApiUser). I cron lo inviano nel
 * modo standard, `Authorization: Bearer <token>`. Gestim, in push, non può
 * impostare header: appende solo `?token=<token>` all'URL di notifica.
 *
 * Qui, se l'header Authorization manca ma è presente `?token=`, lo
 * sintetizziamo: la richiesta viene poi validata da Wonder\Api\Endpoint come
 * una qualunque chiamata Bearer (il token è il JWT firmato con APP_KEY che
 * risolve l'utente @immobili e la sua authority `immobili_sync`).
 */

$hasAuthHeader = !empty($_SERVER['HTTP_AUTHORIZATION'])
    || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);

if (!$hasAuthHeader && function_exists('getallheaders')) {
    $headers = getallheaders();
    $hasAuthHeader = !empty($headers['Authorization']) || !empty($headers['authorization']);
}

if (!$hasAuthHeader) {
    $queryToken = trim((string) ($_GET['token'] ?? ''));

    if ($queryToken !== '') {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer '.$queryToken;
    }
}
