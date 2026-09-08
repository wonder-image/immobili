<?php

/**
 * Card immobile overlay ricca: badge, indirizzo, prezzo e dati sintetici.
 *
 * @var array $args ['immobile' => object, 'gallery' => bool, 'ratio' => string, 'slide_class' => string|string[], 'image_class' => string|string[]]
 */

use Wonder\Plugin\Immobili\Media\CardMedia;

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
$meta = [];

if (trim((string) ($immobile->prettySuperficie ?? '')) !== '') {
    $meta[] = ['icon' => 'bi bi-rulers', 'text' => (string) $immobile->prettySuperficie];
}

if ((int) ($immobile->locali ?? 0) > 0) {
    $meta[] = [
        'icon' => 'bi bi-door-open',
        'text' => (int) $immobile->locali.' '.__t('components.immobili.card.rooms'),
    ];
}

if ((int) ($immobile->camere ?? 0) > 0) {
    $meta[] = ['icon' => 'bi bi-house', 'text' => (string) (int) $immobile->camere];
}

if ((int) ($immobile->bagni ?? 0) > 0) {
    $meta[] = ['icon' => 'bi bi-droplet', 'text' => (string) (int) $immobile->bagni];
}

$media = CardMedia::immobile($immobile, $args);

?>
<a class="d-block p-r b-r-15 o-hidden tx-white" href="<?= e((string) ($immobile->url ?? '#')) ?>">
    <div class="p-r o-hidden">
        <?= $media->render('wonder') ?>

        <div class="p-a top start w-100 d-flex a-items-center gap-2 p-3">
            <?php if (!empty($immobile->sold)) { ?>
                <span class="badge badge-danger tx-upper"><?= e(__t('components.immobili.card.sold')) ?></span>
            <?php } elseif (!empty($immobile->evidence)) { ?>
                <span class="badge badge-dark tx-upper"><?= e(__t('components.immobili.card.featured')) ?></span>
            <?php } ?>
            <?php if ($eyebrow !== '') { ?>
                <span class="badge bg-white-o-20 tx-white"><?= e($eyebrow) ?></span>
            <?php } ?>
        </div>

        <div class="p-a bottom start w-100 p-4 d-grid gap-1 bg-black-o-70 tx-white">
            <div class="text fw-600"><?= e((string) ($immobile->prettyName ?? '')) ?></div>
            <?php if (trim((string) ($immobile->prettyAddress ?? '')) !== '') { ?>
                <div class="text-small"><i class="bi bi-geo-alt"></i> <?= e((string) $immobile->prettyAddress) ?></div>
            <?php } ?>
            <?php if ($prezzo !== '') { ?>
                <div class="text fw-700"><?= e($prezzo) ?></div>
            <?php } ?>
            <?php if ($meta !== []) { ?>
                <div class="d-flex gap-3 text-small mt-1">
                    <?php foreach ($meta as $value) { ?>
                        <span><i class="<?= e($value['icon']) ?>"></i> <?= e($value['text']) ?></span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</a>
