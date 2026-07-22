<?php

/**
 * Card di un immobile in lista. Stile con sole classi utility wonder-image/lib.
 *
 * @var array $args  ['immobile' => object]
 */

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

$cover = (string) ($immobile->cover ?? '');
$url = (string) ($immobile->url ?? '#');

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black" href="<?= e($url) ?>">
    <div class="f-3-2 p-r bg-cover o-hidden" style="background-image:url('<?= e($cover) ?>')">
        <?php if (!empty($immobile->sold)) { ?>
            <span class="p-a badge text-bg-danger" style="top:.6rem;left:.6rem"><?= e(__t('components.immobili.card.sold')) ?></span>
        <?php } elseif (!empty($immobile->evidence)) { ?>
            <span class="p-a badge text-bg-dark" style="top:.6rem;left:.6rem"><?= e(__t('components.immobili.card.featured')) ?></span>
        <?php } ?>
    </div>
    <div class="p-4 d-grid gap-2">
        <?php if (($immobile->tipologia ?? '') !== '') { ?>
            <div class="text-small tx-upper tx-muted"><?= e($immobile->tipologia) ?> · <?= e($immobile->contratto) ?></div>
        <?php } ?>
        <div class="text fw-600"><?= e($immobile->prettyName) ?></div>
        <?php if (($immobile->prettyAddress ?? '') !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($immobile->prettyAddress) ?></div>
        <?php } ?>
        <?php if (($immobile->prezzo ?? '') !== '') { ?>
            <div class="text fw-700 tx-primary"><?= e($immobile->prezzo) ?></div>
        <?php } ?>
        <div class="d-flex gap-4 text-small tx-muted mt-1">
            <?php if (($immobile->superficie ?? '') !== '') { ?>
                <span><i class="bi bi-rulers"></i> <?= e($immobile->superficie) ?></span>
            <?php } ?>
            <?php if (($immobile->locali ?? 0) > 0) { ?>
                <span><i class="bi bi-door-open"></i> <?= (int) $immobile->locali ?> <?= e(__t('components.immobili.card.rooms')) ?></span>
            <?php } ?>
            <?php if (($immobile->camere ?? 0) > 0) { ?>
                <span><i class="bi bi-house"></i> <?= (int) $immobile->camere ?></span>
            <?php } ?>
            <?php if (($immobile->bagni ?? 0) > 0) { ?>
                <span><i class="bi bi-droplet"></i> <?= (int) $immobile->bagni ?></span>
            <?php } ?>
        </div>
    </div>
</a>
