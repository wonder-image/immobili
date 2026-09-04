<?php

/**
 * Griglia di card residenze.
 *
 * Per aggiungere una resa basta creare `residenze/card-{nome}.php` e passare
 * `'card' => 'card-{nome}'`. Ogni card riceve la riga nativa e il presenter.
 *
 * @var array $args [
 *     'residenze' => array[],
 *     'presenter' => \Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter,
 *     'card'      => string,          default 'card-base'
 *     'gallery'   => bool,
 *     'card_args' => array,
 *     'class'     => string|string[],
 * ]
 */

use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\View\View;

$residenze = is_array($args['residenze'] ?? null)
    ? array_values(array_filter($args['residenze'], 'is_array'))
    : [];

if ($residenze === []) {
    return;
}

$presenter = ($args['presenter'] ?? null) instanceof ResidenzaPresenter
    ? $args['presenter']
    : new ResidenzaPresenter();
$card = trim((string) ($args['card'] ?? 'card-base'));

if (preg_match('/^card-[a-z0-9]+(?:-[a-z0-9]+)*$/', $card) !== 1) {
    $card = 'card-base';
}

$cardPath = Immobili::viewPath('components/residenze/'.$card.'.php');

if (!is_file($cardPath)) {
    $card = 'card-base';
    $cardPath = Immobili::viewPath('components/residenze/'.$card.'.php');
}

$extraClasses = $args['class'] ?? [];
$extraClasses = is_array($extraClasses) ? $extraClasses : [$extraClasses];
$extra = [];

foreach ($extraClasses as $value) {
    if (!is_scalar($value)) {
        continue;
    }

    foreach (preg_split('/\s+/', trim((string) $value)) ?: [] as $class) {
        if ($class !== '') {
            $extra[] = $class;
        }
    }
}

$classes = array_values(array_unique(array_merge(
    ['w-100', 'd-grid', 'col-3', 'col-t-2', 'col-p-1', 'gap-5'],
    $extra
)));
$cardArgs = is_array($args['card_args'] ?? null) ? $args['card_args'] : [];
$gallery = (bool) ($args['gallery'] ?? false);

?>
<div class="<?= e(implode(' ', $classes)) ?>">
    <?php foreach ($residenze as $residenza) {
        $renderArgs = $cardArgs;
        $renderArgs['gallery'] = $gallery;
        $renderArgs['presenter'] = $presenter;
        $renderArgs['residenza'] = $residenza;

        echo View::component($cardPath, ['args' => $renderArgs]);
    } ?>
</div>
