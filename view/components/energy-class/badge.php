<?php

/**
 * Classe energetica — tipologia "badge": badge della classe corrente + IPE.
 *
 * Usabile da sola (`['immobile' => $immobile]`) o composta dall'orchestratore
 * (`['scale' => $scale]`). Solo classi di wonder-image/lib; gli unici stili
 * inline sono i colori APE canonici (standard normativo, nessun token equivalente).
 *
 * @var array $args ['immobile' => object] | ['scale' => EnergyScale]
 */

use Wonder\Plugin\Immobili\Support\EnergyScale;

$scale = EnergyScale::fromArgs($args);

if (!$scale instanceof EnergyScale) {
    return;
}

$cols = $scale->hasIpe() ? 'col-2' : 'col-1';

?>

<div class="w-100 d-grid <?= $cols ?> gap-2">

    <div class="a-c p-4 b-r-15" style="background:<?= e($scale->currentBg()) ?>;color:<?= e($scale->currentText()) ?>">
        <div class="small"><?= e(__t('components.immobili.energy.class')) ?></div>
        <div class="title-big fw-700"><?= e($scale->classe()) ?></div>
    </div>

    <?php if ($scale->hasIpe()) { ?>
        <div class="a-c p-4 b-r-15 b-1">
            <div class="small tx-muted"><?= e(__t('components.immobili.energy.ipe')) ?></div>
            <div class="subtitle fw-700"><?= e($scale->ipe()) ?></div>
            <div class="small tx-muted"><?= e(__t('components.immobili.energy.unit')) ?></div>
        </div>
    <?php } ?>

</div>
