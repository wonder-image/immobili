<?php

/**
 * Card di lista: dispatcher delle varianti.
 *
 * Riceve un CardViewModel già pronto — qui non si sa (né si deve sapere) se si
 * sta rendendo un immobile o una residenza — e delega alla variante scelta.
 * Le varianti cambiano solo il markup: leggono tutte gli stessi campi.
 *
 *   base         immagine sopra, corpo su fondo chiaro. Default.
 *   overlay      immagine a tutta card, testo sovrapposto in basso.
 *   overlay-rich come overlay, più indirizzo e badge in alto.
 *
 * La gallery sfogliabile dentro la card è indipendente dalla variante:
 * `'gallery' => true` la abilita, se il view-model porta più di un'immagine.
 *
 * @var array $args [
 *     'item'    => \Wonder\Plugin\Immobili\Catalog\CardViewModel,
 *     'variant' => 'base'|'overlay'|'overlay-rich',   default 'base'
 *     'gallery' => bool,                              default false
 * ]
 */

use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Immobili;

$item = $args['item'] ?? null;

if (!$item instanceof CardViewModel) {
    return;
}

$variant = (string) ($args['variant'] ?? 'base');

// Una variante sconosciuta ricade su `base` invece di non produrre nulla: in
// lista una card mancante è un buco silenzioso, difficile da diagnosticare.
if (!in_array($variant, CardViewModel::VARIANTS, true)) {
    $variant = 'base';
}

Immobili::component('card/'.$variant, [
    'item'    => $item,
    'gallery' => (bool) ($args['gallery'] ?? false),
]);
