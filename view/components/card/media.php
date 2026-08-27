<?php

/**
 * Strati immagine di una card: cover ferma oppure gallery sfogliabile.
 *
 * Produce SOLO gli strati e i controlli, senza contenitore: è la variante a
 * fornire il proprio `<div>` con proporzione e classi, e ad annidarci dentro
 * i suoi overlay (badge, gradiente, testo). Così il comportamento della
 * gallery vive in un posto solo e le varianti restano libere sul markup.
 *
 * La gallery è CSS più un filo di JS delegato: non tira dentro Swiper, che in
 * una griglia costerebbe un'istanza per card. Le frecce compaiono solo quando
 * c'è più di un'immagine.
 *
 * @var array $args [
 *     'item'    => \Wonder\Plugin\Immobili\Catalog\CardViewModel,
 *     'gallery' => bool,
 * ]
 */

use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Immobili;

$item = $args['item'] ?? null;

if (!$item instanceof CardViewModel) {
    return;
}

$images = $item->images !== [] ? $item->images : ($item->cover !== '' ? [$item->cover] : []);

if ($images === []) {
    return;
}

if (!((bool) ($args['gallery'] ?? false)) || count($images) === 1) {
    ?><div class="p-a w-100 h-100 bg-cover" style="background-image:url('<?= e($images[0]) ?>')"></div><?php
    return;
}

Immobili::styleOnce('css/immobili-card.css');
Immobili::scriptOnce('js/immobili-card.js');

foreach ($images as $index => $src) { ?>
    <div class="p-a w-100 h-100 bg-cover immobili-card__slide<?= $index === 0 ? ' is-active' : '' ?>"
         style="background-image:url('<?= e($src) ?>')"></div>
<?php }
?>
<button type="button" class="p-a immobili-card__nav immobili-card__nav--prev" data-immobili-gallery-prev
        aria-label="<?= e(__t('components.immobili.card.gallery_prev')) ?>"><i class="bi bi-chevron-left"></i></button>
<button type="button" class="p-a immobili-card__nav immobili-card__nav--next" data-immobili-gallery-next
        aria-label="<?= e(__t('components.immobili.card.gallery_next')) ?>"><i class="bi bi-chevron-right"></i></button>
<div class="p-a immobili-card__dots">
    <?php foreach ($images as $index => $src) { ?>
        <span class="immobili-card__dot<?= $index === 0 ? ' is-active' : '' ?>"></span>
    <?php } ?>
</div>
