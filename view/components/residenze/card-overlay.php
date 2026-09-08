<?php

/**
 * Card residenza overlay: foto a tutta card, stato e nome.
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
$media = CardMedia::residenza($residenza, $args, $presenter);

?>
<a class="d-block p-r b-r-15 o-hidden tx-white" href="<?= e((string) ($residenza['url'] ?? '#')) ?>">
    <div class="p-r o-hidden">
        <?= $media->render('wonder') ?>
        <span class="p-a top start badge badge-primary tx-upper m-3"><?= e(__t('pages.residenze.stato.'.$stato)) ?></span>

        <div class="p-a bottom start w-100 p-4 d-grid gap-1 bg-black-o-70 tx-white">
            <div class="text fw-600"><?= e((string) ($residenza['nome'] ?? '')) ?></div>
        </div>
    </div>
</a>
