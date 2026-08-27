<?php

/**
 * Variante `overlay`: immagine a tutta card, testo sovrapposto in basso.
 *
 * Sul modello di bgstar.org. Più scenografica della `base`, ma il testo vive
 * sopra la foto: il gradiente scuro in basso è quello che ne garantisce la
 * leggibilità qualunque sia l'immagine, quindi non è decorativo e non va tolto.
 *
 * Tiene solo l'essenziale — titolo e prezzo — perché su una foto ogni riga in
 * più costa leggibilità. Per indirizzo e badge in alto c'è `overlay-rich`.
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

        <?php if ($item->badge !== null) { ?>
            <span class="p-a badge <?= e($item->badge->variant) ?>" style="top:.8rem;left:.8rem"><?= e($item->badge->label) ?></span>
        <?php } ?>

        <div class="p-a w-100 p-4 d-grid gap-1 immobili-card__caption">
            <?php if ($item->eyebrow !== '') { ?>
                <div class="text-small tx-upper immobili-card__eyebrow"><?= e($item->eyebrow) ?></div>
            <?php } ?>
            <div class="text fw-600"><?= e($item->title) ?></div>
            <?php if ($item->highlight !== '') { ?>
                <div class="text fw-700"><?= e($item->highlight) ?></div>
            <?php } ?>
        </div>
    </div>
</a>
