<?php

use Wonder\Api\Endpoint;
use Wonder\Api\Handler;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;

/**
 * Ricerca immobili in JSON (per consumo AJAX/API esterni).
 *
 * GET /api/immobili/search/?comune=&contratto=&prezzo_min=&prezzo_max=&...&pagina=
 *
 * La lista frontend funziona già server-side via form GET; questo endpoint espone
 * gli stessi risultati in JSON (card sintetiche + GeoJSON + paginazione).
 */
Handler::run('/api/immobili/search/', 'GET', ['api_internal_user', 'api_public_access'], function (Endpoint $call) {
    $params = is_array($call->parameters ?? null) ? $call->parameters : [];

    $filters = [
        'q'              => (string) ($params['q'] ?? ''),
        'comune'         => (string) ($params['comune'] ?? ''),
        'contratto'      => (string) ($params['contratto'] ?? ''),
        'tipologia'      => (string) ($params['tipologia'] ?? ''),
        'prezzo_min'     => (int) ($params['prezzo_min'] ?? 0),
        'prezzo_max'     => (int) ($params['prezzo_max'] ?? 0),
        'superficie_min' => (int) ($params['superficie_min'] ?? 0),
        'superficie_max' => (int) ($params['superficie_max'] ?? 0),
        'camere'         => (int) ($params['camere'] ?? 0),
        'bagni'          => (int) ($params['bagni'] ?? 0),
        'ordina'         => (string) ($params['ordina'] ?? 'recenti'),
    ];

    $page = max(1, (int) ($params['page'] ?? 1));
    $perPage = min(60, max(1, (int) ($params['per_page'] ?? 12)));
    $sold = in_array((string) ($params['sold'] ?? ''), ['1', 'true'], true);

    $result = (new ImmobileQuery())->search($filters, $page, $perPage, $sold);

    $items = array_map(static function (object $c): array {
        return [
            'id'         => $c->id,
            'nome'       => $c->prettyName,
            'tipologia'  => $c->tipologia,
            'comune'     => $c->comune,
            'contratto'  => $c->contratto,
            'prezzo'     => $c->prezzo,
            'superficie' => $c->superficie,
            'locali'     => $c->locali,
            'camere'     => $c->camere,
            'bagni'      => $c->bagni,
            'cover'      => $c->cover ?? '',
            'url'        => $c->url,
            'sold'       => $c->sold,
        ];
    }, $result['items']);

    return [
        'success'  => true,
        'status'   => 200,
        'response' => [
            'items'   => $items,
            'total'   => $result['total'],
            'pages'   => $result['pages'],
            'page'    => $result['page'],
            'geojson' => $result['geojson'],
        ],
    ];
});
