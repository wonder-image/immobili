<?php

/**
 * Card immobile overlay: foto a tutta card e contenuto essenziale in basso.
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
$media = CardMedia::immobile($immobile, $args);

?>
<a class="d-block p-r b-r-15 o-hidden tx-white" href="<?= e((string) ($immobile->url ?? '#')) ?>">
    <div class="p-r o-hidden">
        <?= $media->render('wonder') ?>

        <?php if (!empty($immobile->sold)) { ?>
            <span class="p-a top start badge badge-danger tx-upper m-3"><?= e(__t('components.immobili.card.sold')) ?></span>
        <?php } elseif (!empty($immobile->evidence)) { ?>
            <span class="p-a top start badge badge-dark tx-upper m-3"><?= e(__t('components.immobili.card.featured')) ?></span>
        <?php } ?>

        <div class="p-a bottom start w-100 p-4 d-grid gap-1 bg-black-o-70 tx-white">
            <?php if ($eyebrow !== '') { ?>
                <div class="text-small tx-upper"><?= e($eyebrow) ?></div>
            <?php } ?>
            <div class="text fw-600"><?= e((string) ($immobile->prettyName ?? '')) ?></div>
            <?php if ($prezzo !== '') { ?>
                <div class="text fw-700"><?= e($prezzo) ?></div>
            <?php } ?>
        </div>
    </div>
</a>
