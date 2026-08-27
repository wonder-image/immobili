<?php

/**
 * Variante `overlay-rich`: come `overlay`, più indirizzo e badge in alto.
 *
 * Sul modello di panimmre.it. I badge stanno in una riga in alto — dove non
 * competono con il testo del gradiente — e la didascalia guadagna l'indirizzo
 * e la riga di dati sintetici (mq, locali, camere).
 *
 * Rispetto a `overlay` mostra più informazioni sopra la foto: conviene su
 * griglie larghe, dove la card ha spazio. Su colonna stretta la `base` resta
 * più leggibile.
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

Immobili::styleOnce('css/immobili-card.css');

?>
<a class="d-block p-r b-r-15 o-hidden tx-white immobili-card immobili-card--overlay" href="<?= e($item->url) ?>">
    <div class="f-3-2 p-r o-hidden">
        <?php Immobili::component('card/media', ['item' => $item, 'gallery' => $gallery]); ?>

        <div class="p-a w-100 h-100 immobili-card__scrim"></div>

        <div class="p-a w-100 d-flex a-items-center gap-2 p-3 immobili-card__topbar">
            <?php if ($item->badge !== null) { ?>
                <span class="badge <?= e($item->badge->variant) ?>"><?= e($item->badge->label) ?></span>
            <?php } ?>
            <?php if ($item->eyebrow !== '') { ?>
                <span class="badge immobili-card__chip"><?= e($item->eyebrow) ?></span>
            <?php } ?>
        </div>

        <div class="p-a w-100 p-4 d-grid gap-1 immobili-card__caption">
            <div class="text fw-600"><?= e($item->title) ?></div>
            <?php if ($item->subtitle !== '') { ?>
                <div class="text-small immobili-card__eyebrow"><i class="bi bi-geo-alt"></i> <?= e($item->subtitle) ?></div>
            <?php } ?>
            <?php if ($item->highlight !== '') { ?>
                <div class="text fw-700"><?= e($item->highlight) ?></div>
            <?php } ?>
            <?php if ($item->meta !== []) { ?>
                <div class="d-flex gap-3 text-small immobili-card__eyebrow mt-1">
                    <?php foreach ($item->meta as $meta) { ?>
                        <span><i class="<?= e($meta->icon) ?>"></i> <?= e($meta->text) ?></span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</a>
