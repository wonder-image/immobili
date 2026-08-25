<?php

/**
 * Elenco features (icona + label) di una residenza.
 * Args: ['features' => array<int,string> id]
 */

use Wonder\Plugin\Immobili\Support\ResidenzaForm;

$ids = is_array($args['features'] ?? null) ? $args['features'] : [];

if ($ids === []) {
    return;
}

$labels = ResidenzaForm::features();

?>
<div class="d-grid col-2 col-p-1 gap-3 w-100">
    <?php foreach ($ids as $id) {
        $label = (string) ($labels[$id] ?? '');
        if ($label === '') { continue; }
        $icon = ResidenzaForm::featureIcon((string) $id);
    ?>
        <div class="d-flex a-items-center gap-2 text">
            <?php if ($icon !== '') { ?><i class="<?= e($icon) ?>"></i><?php } ?>
            <span><?= e($label) ?></span>
        </div>
    <?php } ?>
</div>
