<?php

/**
 * Card residenza overlay: foto a tutta card, stato e nome.
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
        <span class="p-a badge text-bg-primary tx-upper" style="top:.8rem;left:.8rem"><?= e(__t('pages.residenze.stato.'.$stato)) ?></span>

        <div class="p-a w-100 p-4 d-grid gap-1 immobili-card__caption">
            <div class="text fw-600"><?= e((string) ($residenza['nome'] ?? '')) ?></div>
        </div>
    </div>
</a>
