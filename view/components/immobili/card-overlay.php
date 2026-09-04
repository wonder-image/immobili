<?php

/**
 * Card immobile overlay: foto a tutta card e contenuto essenziale in basso.
 *
 * @var array $args ['immobile' => object, 'gallery' => bool]
 */

use Wonder\Plugin\Immobili\Immobili;

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

$tipologia = trim((string) ($immobile->tipologia ?? ''));
$contratto = trim((string) ($immobile->contratto ?? ''));
$eyebrow = $tipologia !== ''
    ? $tipologia.($contratto !== '' ? ' · '.$contratto : '')
    : '';
$prezzo = trim((string) ($immobile->prezzo ?? '')) !== ''
    ? (string) ($immobile->prettyPrezzo ?? '')
    : '';

Immobili::styleOnce('css/immobili-card.css');

?>
<a class="d-block p-r b-r-15 o-hidden tx-white immobili-card immobili-card--overlay" href="<?= e((string) ($immobile->url ?? '#')) ?>">
    <div class="f-3-2 p-r o-hidden">
        <?php Immobili::component('immobili/card-media', [
            'immobile' => $immobile,
            'gallery' => (bool) ($args['gallery'] ?? false),
        ]); ?>

        <div class="p-a w-100 h-100 immobili-card__scrim"></div>

        <?php if (!empty($immobile->sold)) { ?>
            <span class="p-a badge text-bg-danger" style="top:.8rem;left:.8rem"><?= e(__t('components.immobili.card.sold')) ?></span>
        <?php } elseif (!empty($immobile->evidence)) { ?>
            <span class="p-a badge text-bg-dark" style="top:.8rem;left:.8rem"><?= e(__t('components.immobili.card.featured')) ?></span>
        <?php } ?>

        <div class="p-a w-100 p-4 d-grid gap-1 immobili-card__caption">
            <?php if ($eyebrow !== '') { ?>
                <div class="text-small tx-upper immobili-card__eyebrow"><?= e($eyebrow) ?></div>
            <?php } ?>
            <div class="text fw-600"><?= e((string) ($immobile->prettyName ?? '')) ?></div>
            <?php if ($prezzo !== '') { ?>
                <div class="text fw-700"><?= e($prezzo) ?></div>
            <?php } ?>
        </div>
    </div>
</a>
