<?php

/**
 * Griglia responsive di card immobiliari.
 *
 * @var array $args [
 *     'immobili' => object[],
 *     'class'     => string|string[],
 * ]
 */

use Wonder\Plugin\Immobili\Immobili;

$immobili = $args['immobili'] ?? [];
$immobili = is_array($immobili)
    ? array_values(array_filter($immobili, 'is_object'))
    : [];

if ($immobili === []) {
    return;
}

$classes = ['w-100', 'd-grid', 'col-3', 'col-t-2', 'col-p-1', 'gap-5'];
$extraClasses = $args['class'] ?? [];
$extraClasses = is_array($extraClasses) ? $extraClasses : [$extraClasses];

foreach ($extraClasses as $value) {
    if (!is_scalar($value)) {
        continue;
    }

    foreach (preg_split('/\s+/', trim((string) $value)) ?: [] as $class) {
        if ($class !== '') {
            $classes[] = $class;
        }
    }
}

$classes = array_values(array_unique($classes));

?>
<div class="<?= e(implode(' ', $classes)) ?>">
    <?php foreach ($immobili as $immobile) {
        Immobili::component('card', ['immobile' => $immobile]);
    } ?>
</div>
