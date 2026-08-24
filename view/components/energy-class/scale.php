<?php

/**
 * Classe energetica — tipologia "scale": scala completa verticale, bande di
 * larghezza crescente dalla classe migliore alla peggiore, attuale evidenziata.
 * Solo classi di wonder-image/lib; inline solo colore (APE) e larghezza (dato).
 *
 * @var array $args ['immobile' => object] | ['scale' => EnergyScale]
 */

use Wonder\Plugin\Immobili\Support\EnergyScale;

$scale = EnergyScale::fromArgs($args);

if (!$scale instanceof EnergyScale) {
    return;
}

?>

<div class="w-100 d-flex d-column gap-2">
    <?php foreach ($scale->bands() as $band) { ?>
        <div class="d-flex center j-content-start p-2 <?= $band['current'] ? 'fw-700 b-1 b-shadow' : 'fw-600' ?>" style="width:<?= (int) $band['width'] ?>%;background:<?= e($band['bg']) ?>;color:<?= e($band['text']) ?>">
            <?= e($band['label']) ?>
        </div>
    <?php } ?>
</div>
