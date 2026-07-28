<?php

/**
 * Carosello responsive di card immobiliari.
 *
 * @var array $args [
 *     'immobili'    => object[],
 *     'id'           => string,
 *     'class'        => string|string[],
 *     'slide_class'  => string|string[],
 *     'aria_label'   => string,
 * ]
 */

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\View\Component as ViewComponent;

$immobili = $args['immobili'] ?? [];
$immobili = is_array($immobili)
    ? array_values(array_filter($immobili, 'is_object'))
    : [];

if ($immobili === []) {
    return;
}

$cardPath = Immobili::viewPath('components/card.php');
$cards = array_map(
    static fn (object $immobile): ViewComponent => ViewComponent::make(
        $cardPath,
        ['args' => ['immobile' => $immobile]]
    ),
    $immobili
);

Dependencies::swiper();

$swiper = __swiper()
    ->slides($cards)
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

$classes = $args['class'] ?? [];
$classes = is_array($classes) ? $classes : [$classes];
$classes = implode(' ', array_filter(array_map(
    static fn (mixed $class): string => is_scalar($class) ? trim((string) $class) : '',
    $classes
)));

if ($classes !== '') {
    $swiper->addClass($classes);
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
