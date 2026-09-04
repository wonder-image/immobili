<?php

/**
 * Slideshow di card immobili.
 *
 * `card` punta a un file `immobili/card-{nome}.php`; anche le varianti
 * presenti soltanto negli override del sito vengono risolte automaticamente.
 *
 * @var array $args [
 *     'immobili'   => object[],
 *     'card'       => string,          default 'card-base'
 *     'gallery'    => bool,
 *     'card_args'  => array,
 *     'class'      => string|string[],
 *     'id'         => string,
 *     'slide_class'=> string|string[],
 *     'aria_label' => string,
 * ]
 */

use Wonder\App\Dependencies;
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

$cardArgs = is_array($args['card_args'] ?? null) ? $args['card_args'] : [];
$gallery = (bool) ($args['gallery'] ?? false);
$slides = [];

foreach ($immobili as $immobile) {
    $renderArgs = $cardArgs;
    $renderArgs['gallery'] = $gallery;
    $renderArgs['immobile'] = $immobile;
    $slides[] = View::component($cardPath, ['args' => $renderArgs]);
}

Dependencies::swiper();

$swiper = __swiper()
    ->slides($slides)
    ->slidesPerView(1.05)
    ->spaceBetween(16)
    ->breakpoints([
        769 => ['slidesPerView' => 2, 'spaceBetween' => 20],
        993 => ['slidesPerView' => 3, 'spaceBetween' => 20],
    ])
    ->autoHeight()
    ->keyboard()
    ->watchOverflow()
    ->navigation();

$id = trim((string) ($args['id'] ?? ''));

if ($id !== '') {
    $swiper->id($id);
}

$extraClasses = $args['class'] ?? [];
$extraClasses = is_array($extraClasses) ? $extraClasses : [$extraClasses];

foreach ($extraClasses as $value) {
    if (is_scalar($value) && trim((string) $value) !== '') {
        $swiper->addClass(trim((string) $value));
    }
}

$slideClasses = $args['slide_class'] ?? [];

if (
    (is_string($slideClasses) && trim($slideClasses) !== '')
    || (is_array($slideClasses) && $slideClasses !== [])
) {
    $swiper->slideClass($slideClasses);
}

$ariaLabel = trim((string) ($args['aria_label'] ?? ''));

if ($ariaLabel !== '') {
    $swiper->attr('aria-label', $ariaLabel);
}

echo $swiper->render('wonder');
