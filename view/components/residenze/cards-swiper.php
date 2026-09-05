<?php

/**
 * Slideshow di card residenze.
 *
 * `card` punta a un file `residenze/card-{nome}.php`; anche le varianti
 * presenti soltanto negli override del sito vengono risolte automaticamente.
 *
 * @var array $args [
 *     'residenze'   => array[],
 *     'presenter'   => \Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter,
 *     'card'        => string,          default 'card-base'
 *     'gallery'     => bool,
 *     'card_args'   => array,
 *     'class'       => string|string[],
 *     'id'          => string,
 *     'slide_class' => string|string[],
 *     'aria_label'  => string,
 * ]
 */

use Wonder\App\Dependencies;
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

$cardArgs = is_array($args['card_args'] ?? null) ? $args['card_args'] : [];
$gallery = (bool) ($args['gallery'] ?? false);
$slides = [];

foreach ($residenze as $residenza) {
    $renderArgs = $cardArgs;
    $renderArgs['gallery'] = $gallery;
    $renderArgs['presenter'] = $presenter;
    $renderArgs['residenza'] = $residenza;
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
    ->navigation()
    ->addClass('o-unset');

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
