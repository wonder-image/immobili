<?php

/**
 * Classe energetica — tipologia "badge": pastiglia colorata con la classe +
 * valore IPE. Stile in resources/assets/css/immobili-energy.css; inline solo il
 * colore APE della classe corrente (dato) via custom properties --ie-*.
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

<div class="w-100 immobili-energy__badge">

    <div class="immobili-energy__chip" style="--ie-bg:<?= e($scale->currentBg()) ?>;--ie-fg:<?= e($scale->currentText()) ?>">
        <?= e($scale->classe()) ?>
    </div>

    <?php if ($scale->hasIpe()) { ?>
        <div class="immobili-energy__ipe">
            <div class="text-small tx-muted tx-upper"><?= e(__t('components.immobili.energy.ipe')) ?></div>
            <div>
                <span class="text immobili-energy__ipe-value"><?= e($scale->ipe()) ?></span>
                <span class="text-small tx-muted"><?= e(__t('components.immobili.energy.unit')) ?></span>
            </div>
        </div>
    <?php } else { ?>
        <div class="text tx-muted"><?= e(__t('components.immobili.energy.class')) ?></div>
    <?php } ?>

</div>
