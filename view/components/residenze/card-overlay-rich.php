<?php

/**
 * Card residenza overlay ricca: stato, comune e timeline.
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

Immobili::styleOnce('css/immobili-card.css');

?>
<a class="d-block p-r b-r-15 o-hidden tx-white immobili-card immobili-card--overlay" href="<?= e((string) ($residenza['url'] ?? '#')) ?>">
    <div class="f-3-2 p-r o-hidden">
        <?php Immobili::component('residenze/card-media', [
            'residenza' => $residenza,
            'presenter' => $presenter,
            'gallery' => (bool) ($args['gallery'] ?? false),
        ]); ?>

        <div class="p-a w-100 h-100 immobili-card__scrim"></div>

        <div class="p-a w-100 d-flex a-items-center gap-2 p-3 immobili-card__topbar">
            <span class="badge text-bg-primary tx-upper"><?= e(__t('pages.residenze.stato.'.$stato)) ?></span>
        </div>

        <div class="p-a w-100 p-4 d-grid gap-1 immobili-card__caption">
            <div class="text fw-600"><?= e((string) ($residenza['nome'] ?? '')) ?></div>
            <?php if (trim((string) ($residenza['comune_nome'] ?? '')) !== '') { ?>
                <div class="text-small immobili-card__eyebrow"><i class="bi bi-geo-alt"></i> <?= e((string) $residenza['comune_nome']) ?></div>
            <?php } ?>
            <?php if ($timeline !== '') { ?>
                <div class="d-flex gap-3 text-small immobili-card__eyebrow mt-1">
                    <span><i class="bi bi-calendar3"></i> <?= e($timeline) ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</a>
