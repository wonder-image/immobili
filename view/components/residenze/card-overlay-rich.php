<?php

/**
 * Card residenza overlay ricca: stato, comune e timeline.
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
<a class="d-block p-r b-r-15 o-hidden tx-white" href="<?= e((string) ($residenza['url'] ?? '#')) ?>">
    <div class="p-r o-hidden">
        <?= $media->render('wonder') ?>

        <div class="p-a top start w-100 d-flex a-items-center gap-2 p-3">
            <span class="badge badge-primary tx-upper"><?= e(__t('pages.residenze.stato.'.$stato)) ?></span>
        </div>

        <div class="p-a bottom start w-100 p-4 d-grid gap-1 bg-black-o-70 tx-white">
            <div class="text fw-600"><?= e((string) ($residenza['nome'] ?? '')) ?></div>
            <?php if (trim((string) ($residenza['comune_nome'] ?? '')) !== '') { ?>
                <div class="text-small"><i class="bi bi-geo-alt"></i> <?= e((string) $residenza['comune_nome']) ?></div>
            <?php } ?>
            <?php if ($timeline !== '') { ?>
                <div class="d-flex gap-3 text-small mt-1">
                    <span><i class="bi bi-calendar3"></i> <?= e($timeline) ?></span>
                </div>
            <?php } ?>
        </div>
    </div>
</a>
