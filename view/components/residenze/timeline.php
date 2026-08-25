<?php

/**
 * Timeline anno/mese di una residenza: inizio → fine stimata.
 * Args: ['inizio' => string, 'fine' => string, 'stato' => string]
 * Solo classi utility wonder-image/lib.
 */

$inizio = trim((string) ($args['inizio'] ?? ''));
$fine   = trim((string) ($args['fine'] ?? ''));
$stato  = trim((string) ($args['stato'] ?? ''));

if ($inizio === '' && $fine === '') {
    return;
}

?>
<div class="d-flex a-items-center gap-3 w-100">
    <div class="d-flex d-column a-items-center">
        <span class="text-small tx-muted"><?= e(__t('pages.residenze.detail.timeline')) ?></span>
    </div>
    <div class="d-flex a-items-center gap-2 fw-600">
        <?php if ($inizio !== '') { ?><span class="text"><?= e($inizio) ?></span><?php } ?>
        <span class="tx-muted">→</span>
        <?php if ($fine !== '') { ?><span class="text"><?= e($fine) ?></span><?php } ?>
    </div>
    <?php if ($stato !== '') { ?>
        <span class="badge text-bg-primary tx-upper"><?= e($stato) ?></span>
    <?php } ?>
</div>
