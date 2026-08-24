<?php

/**
 * Classe energetica (APE) — orchestratore.
 *
 * Compone le tre tipologie della cartella (badge, line, scale), separate da un
 * divisore, e le nasconde tutte se la classe energetica è assente. Le tre parti
 * restano usabili anche singolarmente:
 *   Immobili::component('energy-class/badge', ['immobile' => $immobile]);
 *
 * Solo classi di wonder-image/lib: nessun CSS custom.
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

?>

<div class="w-100">

    <?php if ($heading) { ?>
        <div class="text-small tx-muted tx-upper"><?= e(__t('components.immobili.energy.title')) ?></div>
    <?php } ?>

    <?php foreach ($parts as $i => $part) { ?>
        <?php if ($i > 0) { ?>
            <div class="bb-1 w-100 mt-4 mb-4"></div>
        <?php } elseif ($heading) { ?>
            <div class="mt-3"></div>
        <?php } ?>
        <?php Immobili::component('energy-class/'.$part, ['scale' => $scale]); ?>
    <?php } ?>

</div>
