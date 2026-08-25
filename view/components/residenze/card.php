<?php

/**
 * Card di una residenza in lista. Solo classi utility wonder-image/lib.
 * Args: ['residenza' => array riga decorata, 'presenter' => ResidenzaPresenter]
 */

use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;

$row = is_array($args['residenza'] ?? null) ? $args['residenza'] : null;

if ($row === null) {
    return;
}

$presenter = $args['presenter'] ?? new ResidenzaPresenter();
$cover = $presenter->cover($row);
$url = (string) ($row['url'] ?? '#');
$nome = (string) ($row['nome'] ?? '');
$comune = (string) ($row['comune_nome'] ?? '');
$breve = (string) ($row['descrizione_breve'] ?? '');
$stato = ResidenzaPresenter::stato($row);
$statoLabel = (string) __t('pages.residenze.stato.'.$stato);
$timeline = trim(
    ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0))
    .' → '.
    ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0)),
    ' →'
);

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($url) ?>">
    <div class="f-3-2 p-r bg-cover o-hidden" style="background-image:url('<?= e($cover) ?>')">
        <span class="p-a badge text-bg-primary tx-upper" style="top:.6rem;left:.6rem"><?= e($statoLabel) ?></span>
    </div>
    <div class="p-4 d-grid gap-2">
        <div class="text fw-700"><?= e($nome) ?></div>
        <?php if ($comune !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($comune) ?></div>
        <?php } ?>
        <?php if ($timeline !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-calendar3"></i> <?= e($timeline) ?></div>
        <?php } ?>
        <?php if ($breve !== '') { ?>
            <div class="text-small mt-1"><?= e($breve) ?></div>
        <?php } ?>
    </div>
</a>
