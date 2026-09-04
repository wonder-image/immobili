<?php

/**
 * Card residenza base: immagine sopra e dati su fondo chiaro.
 *
 * @var array $args [
 *     'residenza' => array,
 *     'presenter' => \Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter,
 *     'gallery'   => bool,
 * ]
 */

use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;
use Wonder\Plugin\Immobili\Immobili;

$residenza = is_array($args['residenza'] ?? null) ? $args['residenza'] : null;

if ($residenza === null) {
    return;
}

$presenter = ($args['presenter'] ?? null) instanceof ResidenzaPresenter
    ? $args['presenter']
    : new ResidenzaPresenter();
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

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e((string) ($residenza['url'] ?? '#')) ?>">
    <div class="f-3-2 p-r o-hidden">
        <?php Immobili::component('residenze/card-media', [
            'residenza' => $residenza,
            'presenter' => $presenter,
            'gallery' => (bool) ($args['gallery'] ?? false),
        ]); ?>
        <span class="p-a badge text-bg-primary tx-upper" style="top:.6rem;left:.6rem"><?= e(__t('pages.residenze.stato.'.$stato)) ?></span>
    </div>
    <div class="p-4 d-grid gap-2">
        <div class="text fw-700"><?= e((string) ($residenza['nome'] ?? '')) ?></div>
        <?php if (trim((string) ($residenza['comune_nome'] ?? '')) !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e((string) $residenza['comune_nome']) ?></div>
        <?php } ?>
        <?php if ($timeline !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-calendar3"></i> <?= e($timeline) ?></div>
        <?php } ?>
        <?php if (trim((string) ($residenza['descrizione_breve'] ?? '')) !== '') { ?>
            <div class="text-small mt-1"><?= e((string) $residenza['descrizione_breve']) ?></div>
        <?php } ?>
    </div>
</a>
