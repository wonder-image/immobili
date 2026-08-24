<?php

/**
 * Classe energetica — tipologia "line": tutte le classi in fila, quella attuale
 * evidenziata (bordo + ombra + grassetto, tutto con classi di wonder-image/lib).
 *
 * @var array $args ['immobile' => object] | ['scale' => EnergyScale]
 */

use Wonder\Plugin\Immobili\Support\EnergyScale;

$scale = EnergyScale::fromArgs($args);

if (!$scale instanceof EnergyScale) {
    return;
}

$bands = $scale->bands();

?>

<div class="w-100 d-grid col-<?= count($bands) ?> gap-2" role="img" aria-label="<?= e(__t('components.immobili.energy.title').': '.$scale->classe()) ?>">
    <?php foreach ($bands as $band) { ?>
        <div class="a-c small pt-2 pb-2 <?= $band['current'] ? 'fw-700 b-1 b-shadow' : 'fw-600' ?>" style="background:<?= e($band['bg']) ?>;color:<?= e($band['text']) ?>">
            <?= e($band['label']) ?>
        </div>
    <?php } ?>
</div>
