<?php

/**
 * Classe energetica (APE) — orchestratore.
 *
 * Compone le tre tipologie della cartella (badge, line, scale), separate da un
 * divisore leggero, e le nasconde tutte se la classe energetica è assente. Le
 * tre parti restano usabili anche singolarmente:
 *   Immobili::component('energy-class/badge', ['immobile' => $immobile]);
 *
 * Stile in resources/assets/css/immobili-energy.css (consuma i token di lib).
 *
 * @var array $args [
 *     'immobile' => object,     // obbligatorio
 *     'show'     => string[],   // subset di ['badge','line','scale'], default tutte
 *     'heading'  => bool,       // mostra il titolo, default true
 * ]
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Support\EnergyScale;

$scale = EnergyScale::fromArgs($args);

if (!$scale instanceof EnergyScale) {
    return;
}

$allowed = ['badge', 'line', 'scale'];
$parts = is_array($args['show'] ?? null)
    ? array_values(array_intersect($allowed, $args['show']))
    : $allowed;

if ($parts === []) {
    return;
}

$heading = (bool) ($args['heading'] ?? true);

Immobili::styleOnce('css/immobili-energy.css');

?>

<div class="immobili-energy">

    <?php if ($heading) { ?>
        <div class="text-small tx-muted tx-upper"><?= e(__t('components.immobili.energy.title')) ?></div>
    <?php } ?>

    <?php foreach ($parts as $part) { ?>
        <div class="w-100 immobili-energy__part">
            <?php Immobili::component('energy-class/'.$part, ['scale' => $scale]); ?>
        </div>
    <?php } ?>

</div>
