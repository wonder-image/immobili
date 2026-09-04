<?php

/**
 * Griglia di card immobili.
 *
 * Per aggiungere una resa basta creare `immobili/card-{nome}.php` e passare
 * `'card' => 'card-{nome}'`. Il componente riceve direttamente l'oggetto
 * prodotto da ImmobileQuery::cards(), senza view-model intermedi.
 *
 * @var array $args [
 *     'immobili'  => object[],
 *     'card'      => string,          default 'card-base'
 *     'gallery'   => bool,
 *     'card_args' => array,
 *     'class'     => string|string[],
 * ]
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\View\View;

$immobili = is_array($args['immobili'] ?? null)
    ? array_values(array_filter($args['immobili'], 'is_object'))
    : [];

if ($immobili === []) {
    return;
}

$card = trim((string) ($args['card'] ?? 'card-base'));

if (preg_match('/^card-[a-z0-9]+(?:-[a-z0-9]+)*$/', $card) !== 1) {
    $card = 'card-base';
}

$cardPath = Immobili::viewPath('components/immobili/'.$card.'.php');

if (!is_file($cardPath)) {
    $card = 'card-base';
    $cardPath = Immobili::viewPath('components/immobili/'.$card.'.php');
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
    <?php foreach ($immobili as $immobile) {
        $renderArgs = $cardArgs;
        $renderArgs['gallery'] = $gallery;
        $renderArgs['immobile'] = $immobile;

        echo View::component($cardPath, ['args' => $renderArgs]);
    } ?>
</div>
