<?php

/**
 * Classe energetica — tipologia "scale": scala verticale, bande di larghezza
 * crescente. Le classi diverse dall'attuale sono attenuate; l'attuale è a piena
 * tinta, in grassetto, con freccia e contorno. Stile in immobili-energy.css;
 * inline solo colore APE e larghezza (dati) via --ie-*.
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

<div class="w-100 immobili-energy__scale">
    <?php foreach ($scale->bands() as $band) { ?>
        <div class="immobili-energy__band<?= $band['current'] ? ' immobili-energy__band--current' : '' ?>" style="--ie-bg:<?= e($band['bg']) ?>;--ie-fg:<?= e($band['text']) ?>;--ie-w:<?= (int) $band['width'] ?>%">
            <span><?= e($band['label']) ?></span>
            <?php if ($band['current']) { ?>
                <i class="bi bi-caret-left-fill immobili-energy__caret" aria-hidden="true"></i>
            <?php } ?>
        </div>
    <?php } ?>
</div>
