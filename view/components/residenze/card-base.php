<?php

/**
 * Card residenza base: immagine sopra e dati su fondo chiaro.
 *
 * @var array $args [
 *     'residenza' => array,
 *     'presenter' => \Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter,
 *     'gallery'   => bool,
 *     'ratio'     => string,
 *     'slide_class' => string|string[],
 *     'image_class' => string|string[],
 * ]
 */

use Wonder\Plugin\Immobili\Media\CardMedia;
use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;

$residenza = is_array($args['residenza'] ?? null) ? $args['residenza'] : null;

if ($residenza === null) {
    return;
}

$presenter = ($args['presenter'] ?? null) instanceof ResidenzaPresenter
    ? $args['presenter']
    : new ResidenzaPresenter();
$prettyAddress = $presenter->prettyAddress($residenza);
$stato = ResidenzaPresenter::stato($residenza);
$timeline = trim(
    ResidenzaPresenter::timelineLabel(
        (int) ($residenza['inizio_anno'] ?? 0),
        (int) ($residenza['inizio_mese'] ?? 0)
    )
    .' → '.
    ResidenzaPresenter::timelineLabel(
        (int) ($residenza['fine_anno'] ?? 0),
        (int) ($residenza['fine_mese'] ?? 0)
    ),
    ' →'
);
$media = CardMedia::residenza($residenza, $args, $presenter);

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e((string) ($residenza['url'] ?? '#')) ?>">
    <div class="p-r o-hidden">
        <?= $media->render('wonder') ?>
        <span class="p-a top start badge badge-primary tx-upper m-3"><?= e(__t('pages.residenze.stato.'.$stato)) ?></span>
    </div>
    <div class="p-4 d-grid gap-2">
        <div class="text fw-700"><?= e((string) ($residenza['nome'] ?? '')) ?></div>
        <?php if ($prettyAddress !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($prettyAddress) ?></div>
        <?php } ?>
        <?php if ($timeline !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-calendar3"></i> <?= e($timeline) ?></div>
        <?php } ?>
        <?php if (trim((string) ($residenza['descrizione_breve'] ?? '')) !== '') { ?>
            <div class="text-small mt-1"><?= e((string) $residenza['descrizione_breve']) ?></div>
        <?php } ?>
    </div>
</a>
