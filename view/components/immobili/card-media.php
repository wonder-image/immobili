<?php

/**
 * Immagine o gallery interna di una card immobile.
 *
 * @var array $args ['immobile' => object, 'gallery' => bool]
 */

use Wonder\Plugin\Immobili\Immobili;

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

$cover = trim((string) ($immobile->cover ?? ''));
$images = array_values(array_filter(
    array_map(
        static fn ($image): string => is_string($image)
            ? trim($image)
            : (is_object($image) ? trim((string) ($image->src ?? '')) : ''),
        is_array($immobile->images ?? null) ? $immobile->images : []
    ),
    static fn (string $src): bool => $src !== ''
));

if ($images === [] && $cover !== '') {
    $images = [$cover];
}

if ($images === []) {
    return;
}

if (!(bool) ($args['gallery'] ?? false) || count($images) === 1) {
    ?><div class="p-a w-100 h-100 bg-cover" style="background-image:url('<?= e($images[0]) ?>')"></div><?php
    return;
}

Immobili::styleOnce('css/immobili-card.css');
Immobili::scriptOnce('js/immobili-card.js');

foreach ($images as $index => $src) { ?>
    <div class="p-a w-100 h-100 bg-cover immobili-card__slide<?= $index === 0 ? ' is-active' : '' ?>"
         style="background-image:url('<?= e($src) ?>')"></div>
<?php } ?>
<button type="button" class="p-a immobili-card__nav immobili-card__nav--prev" data-immobili-gallery-prev
        aria-label="<?= e(__t('components.immobili.card.gallery_prev')) ?>"><i class="bi bi-chevron-left"></i></button>
<button type="button" class="p-a immobili-card__nav immobili-card__nav--next" data-immobili-gallery-next
        aria-label="<?= e(__t('components.immobili.card.gallery_next')) ?>"><i class="bi bi-chevron-right"></i></button>
<div class="p-a immobili-card__dots">
    <?php foreach ($images as $index => $_src) { ?>
        <span class="immobili-card__dot<?= $index === 0 ? ' is-active' : '' ?>"></span>
    <?php } ?>
</div>
