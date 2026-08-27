<?php

/**
 * Collezione di card, comune ai due reparti e ai due layout.
 *
 * @var array $args [
 *     'items'       => \Wonder\Plugin\Immobili\Catalog\CardViewModel[],
 *     'layout'      => 'grid'|'swiper',   default 'grid'
 *     'class'       => string|string[],
 *     'id'          => string,            solo swiper
 *     'slide_class' => string|string[],   solo swiper
 *     'aria_label'  => string,            solo swiper
 * ]
 */

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\View\Component as ViewComponent;

$items = $args['items'] ?? [];
$items = is_array($items)
    ? array_values(array_filter($items, static fn ($i): bool => $i instanceof CardViewModel))
    : [];

if ($items === []) {
    return;
}

$layout = ($args['layout'] ?? 'grid') === 'swiper' ? 'swiper' : 'grid';

// Classi extra: accettate come stringa o array, normalizzate a lista di token.
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

if ($layout === 'grid') {
    $classes = array_values(array_unique(array_merge(
        ['w-100', 'd-grid', 'col-3', 'col-t-2', 'col-p-1', 'gap-5'],
        $extra
    )));
    ?>
    <div class="<?= e(implode(' ', $classes)) ?>">
        <?php foreach ($items as $item) {
            Immobili::component('card', ['item' => $item]);
        } ?>
    </div>
    <?php
    return;
}

$cardPath = Immobili::viewPath('components/card.php');
$slides = array_map(
    static fn (CardViewModel $item): ViewComponent => ViewComponent::make(
        $cardPath,
        ['args' => ['item' => $item]]
    ),
    $items
);

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

if ($extra !== []) {
    $swiper->addClass(implode(' ', $extra));
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
