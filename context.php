<?php

/**
 * Stato condiviso del modulo Immobili.
 *
 * Caricato da `Immobili::context()` e memoizzato per la richiesta. Espone il
 * locale corrente, i feed attivi e i filtri correnti (letti dalla query string)
 * usati dalla lista frontend. Le liste di opzioni per i filtri (comuni,
 * tipologie, ...) vengono calcolate nella pagina lista per non appesantire ogni
 * richiesta.
 *
 * È volutamente difensivo: durante il primo setup (DB non ancora migrato) non
 * deve mai generare fatal error.
 */

use Wonder\Plugin\Immobili\Models\FeedSource;

$locale = __l();

$feeds = [];

if (class_exists(FeedSource::class)) {
    try {
        $rows = FeedSource::find(['active' => 'true', 'deleted' => 'false']);
        if (is_array($rows)) {
            // find() con limite può restituire una singola riga associativa.
            $feeds = isset($rows['id']) ? [$rows] : array_values($rows);
        }
    } catch (\Throwable) {
        $feeds = [];
    }
}

$param = static fn (string $key): string => trim((string) ($_GET[$key] ?? ''));
$paramInt = static fn (string $key): int => (int) ($_GET[$key] ?? 0);

$filters = [
    'q'              => $param('q'),
    'comune'         => $param('comune'),
    'contratto'      => $param('contratto'),
    'categoria'      => $param('categoria'),
    'tipologia'      => $param('tipologia'),
    'prezzo_min'     => $paramInt('prezzo_min'),
    'prezzo_max'     => $paramInt('prezzo_max'),
    'superficie_min' => $paramInt('superficie_min'),
    'superficie_max' => $paramInt('superficie_max'),
    'camere'         => $paramInt('camere'),
    'bagni'          => $paramInt('bagni'),
    'ordina'         => $param('ordina') ?: 'recenti',
    'pagina'         => max(1, $paramInt('page')),
];

return [
    'locale'   => $locale,
    'feeds'    => $feeds,
    'filters'  => $filters,
    'per_page' => 12,
];
