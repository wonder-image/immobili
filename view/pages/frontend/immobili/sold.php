<?php

/**
 * Immobili venduti (portfolio): griglia + mappa, senza filtri.
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;

$PAGE_KEY = 'immobili.sold';

$SEO->title = __t('pages.immobili.sold.seo.title');
$SEO->description = __t('pages.immobili.sold.seo.description');
$SEO->url = __r('immobili.sold');
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    $SEO->url => __t('components.navigation.immobili_sold')
];
$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

$state = Immobili::context();
$perPage = (int) ($state['per_page'] ?? 12);

$query = new ImmobileQuery();
$where = $query->where([], true);
[$order, $direction] = $query->order('recenti');

$PAGINATION = pagination('immobili', $where, $perPage);
$rows = Immobile::safeFind($where, $PAGINATION->limit, $order, $direction) ?? [];

$items = CardViewModel::fromImmobili($query->cards($rows));
$geojson = $query->geojson($where);

Immobili::layout('main');

?>

<section class="content mt-8">
    <h1 class="title-big"><?= e(__t('pages.immobili.sold.title')) ?></h1>
    <p class="text tx-muted mt-2"><?= e(__t('pages.immobili.sold.subtitle')) ?></p>
</section>

<?php if (!empty($geojson)) { ?>
<section class="content mt-6">
    <?php Immobili::component('map', ['features' => $geojson]); ?>
</section>
<?php } ?>

<section class="content mt-6 mb-10">
    <?php if ($items === []) { ?>
        <p class="text"><?= e(__t('pages.immobili.sold.empty')) ?></p>
    <?php } else { ?>
        <?php Immobili::component('cards', [
            'items' => $items,
            'class' => 'mt-4',
        ]); ?>
    <?php } ?>

    <div class="d-flex j-content-center mt-8">
        <?= $PAGINATION->html ?>
    </div>
</section>

<?php \Wonder\View\View::end(); ?>
