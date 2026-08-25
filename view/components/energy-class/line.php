<?php

/**
 * Classe energetica — tipologia "line": tutte le classi in una riga compatta;
 * quella attuale a piena tinta con contorno primario, le altre attenuate.
 * Stile in immobili-energy.css; inline solo il colore APE (dato) via --ie-*.
 *
 * @var array $args ['immobile' => object] | ['scale' => EnergyScale]
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Support\EnergyScale;

$scale = EnergyScale::fromArgs($args);

if (!$scale instanceof EnergyScale) {
    return;
}

Immobili::styleOnce('css/immobili-energy.css');

?>

<div class="w-100 immobili-energy__line" role="img" aria-label="<?= e(__t('components.immobili.energy.title').': '.$scale->classe()) ?>">
    <?php foreach ($scale->bands() as $band) { ?>
        <div class="immobili-energy__cell<?= $band['current'] ? ' immobili-energy__cell--current' : '' ?>" style="--ie-bg:<?= e($band['bg']) ?>;--ie-fg:<?= e($band['text']) ?>">
            <?= e($band['label']) ?>
        </div>
    <?php } ?>
</div>
