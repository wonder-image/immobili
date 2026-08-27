<?php

/**
 * Variante `base`: immagine sopra, corpo su fondo chiaro.
 *
 * È il default e la variante più leggibile su liste lunghe: il testo sta su
 * fondo pieno, non sopra una foto, quindi il contrasto non dipende
 * dall'immagine. Solo classi utility wonder-image/lib.
 *
 * @var array $args ['item' => CardViewModel, 'gallery' => bool]
 */

use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Immobili;

$item = $args['item'] ?? null;

if (!$item instanceof CardViewModel) {
    return;
}

$gallery = (bool) ($args['gallery'] ?? false);

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($item->url) ?>">
    <div class="f-3-2 p-r o-hidden">
        <?php Immobili::component('card/media', ['item' => $item, 'gallery' => $gallery]); ?>
        <?php if ($item->badge !== null) { ?>
            <span class="p-a badge <?= e($item->badge->variant) ?>" style="top:.6rem;left:.6rem"><?= e($item->badge->label) ?></span>
        <?php } ?>
    </div>
    <div class="p-4 d-grid gap-2">
        <?php if ($item->eyebrow !== '') { ?>
            <div class="text-small tx-upper tx-muted"><?= e($item->eyebrow) ?></div>
        <?php } ?>
        <div class="text fw-600"><?= e($item->title) ?></div>
        <?php if ($item->subtitle !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($item->subtitle) ?></div>
        <?php } ?>
        <?php if ($item->highlight !== '') { ?>
            <div class="text fw-700 tx-primary"><?= e($item->highlight) ?></div>
        <?php } ?>
        <?php if ($item->excerpt !== '') { ?>
            <div class="text-small mt-1"><?= e($item->excerpt) ?></div>
        <?php } ?>
        <?php if ($item->meta !== []) { ?>
            <div class="d-flex gap-4 text-small tx-muted mt-1">
                <?php foreach ($item->meta as $meta) { ?>
                    <span><i class="<?= e($meta->icon) ?>"></i> <?= e($meta->text) ?></span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</a>
