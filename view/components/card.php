<?php

/**
 * Card di lista, comune ai due reparti. Riceve un CardViewModel già pronto:
 * qui non si sa (né si deve sapere) se si sta rendendo un immobile o una
 * residenza. Solo classi utility wonder-image/lib.
 *
 * @var array $args ['item' => \Wonder\Plugin\Immobili\Catalog\CardViewModel]
 */

use Wonder\Plugin\Immobili\Catalog\CardViewModel;

$item = $args['item'] ?? null;

if (!$item instanceof CardViewModel) {
    return;
}

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($item->url) ?>">
    <div class="f-3-2 p-r bg-cover o-hidden" style="background-image:url('<?= e($item->cover) ?>')">
        <?php if ($item->badge !== null) { ?>
            <span class="p-a badge <?= e($item->badge->variant) ?>" style="top:.6rem;left:.6rem"><?= e($item->badge->label) ?></span>
        <?php } ?>
    </div>
    <div class="p-4 d-grid gap-2">
        <?php if ($item->eyebrow !== '') { ?>
            <div class="text-small tx-upper tx-muted"><?= e($item->eyebrow) ?></div>
        <?php } ?>
        <div class="text fw-600"><?= e($item->title) ?></div>
        <?php if ($item->subtitle !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($item->subtitle) ?></div>
        <?php } ?>
        <?php if ($item->highlight !== '') { ?>
            <div class="text fw-700 tx-primary"><?= e($item->highlight) ?></div>
        <?php } ?>
        <?php if ($item->excerpt !== '') { ?>
            <div class="text-small mt-1"><?= e($item->excerpt) ?></div>
        <?php } ?>
        <?php if ($item->meta !== []) { ?>
            <div class="d-flex gap-4 text-small tx-muted mt-1">
                <?php foreach ($item->meta as $meta) { ?>
                    <span><i class="<?= e($meta->icon) ?>"></i> <?= e($meta->text) ?></span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</a>
