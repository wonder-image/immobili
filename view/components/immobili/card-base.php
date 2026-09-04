<?php

/**
 * Card immobile base: immagine sopra e dati su fondo chiaro.
 *
 * @var array $args ['immobile' => object, 'gallery' => bool]
 */

use Wonder\Plugin\Immobili\Immobili;

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

$url = (string) ($immobile->url ?? '#');
$tipologia = trim((string) ($immobile->tipologia ?? ''));
$contratto = trim((string) ($immobile->contratto ?? ''));
$eyebrow = $tipologia !== ''
    ? $tipologia.($contratto !== '' ? ' · '.$contratto : '')
    : '';
$prezzo = trim((string) ($immobile->prezzo ?? '')) !== ''
    ? (string) ($immobile->prettyPrezzo ?? '')
    : '';
$superficie = trim((string) ($immobile->prettySuperficie ?? ''));

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($url) ?>">
    <div class="f-3-2 p-r o-hidden">
        <?php Immobili::component('immobili/card-media', [
            'immobile' => $immobile,
            'gallery' => (bool) ($args['gallery'] ?? false),
        ]); ?>
        <?php if (!empty($immobile->sold)) { ?>
            <span class="p-a badge text-bg-danger" style="top:.6rem;left:.6rem"><?= e(__t('components.immobili.card.sold')) ?></span>
        <?php } elseif (!empty($immobile->evidence)) { ?>
            <span class="p-a badge text-bg-dark" style="top:.6rem;left:.6rem"><?= e(__t('components.immobili.card.featured')) ?></span>
        <?php } ?>
    </div>
    <div class="p-4 d-grid gap-2">
        <?php if ($eyebrow !== '') { ?>
            <div class="text-small tx-upper tx-muted"><?= e($eyebrow) ?></div>
        <?php } ?>
        <div class="text fw-600"><?= e((string) ($immobile->prettyName ?? '')) ?></div>
        <?php if (trim((string) ($immobile->prettyAddress ?? '')) !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e((string) $immobile->prettyAddress) ?></div>
        <?php } ?>
        <?php if ($prezzo !== '') { ?>
            <div class="text fw-700 tx-primary"><?= e($prezzo) ?></div>
        <?php } ?>
        <div class="d-flex gap-4 text-small tx-muted mt-1">
            <?php if ($superficie !== '') { ?>
                <span><i class="bi bi-rulers"></i> <?= e($superficie) ?></span>
            <?php } ?>
            <?php if ((int) ($immobile->locali ?? 0) > 0) { ?>
                <span><i class="bi bi-door-open"></i> <?= (int) $immobile->locali ?> <?= e(__t('components.immobili.card.rooms')) ?></span>
            <?php } ?>
            <?php if ((int) ($immobile->camere ?? 0) > 0) { ?>
                <span><i class="bi bi-house"></i> <?= (int) $immobile->camere ?></span>
            <?php } ?>
            <?php if ((int) ($immobile->bagni ?? 0) > 0) { ?>
                <span><i class="bi bi-droplet"></i> <?= (int) $immobile->bagni ?></span>
            <?php } ?>
        </div>
    </div>
</a>
