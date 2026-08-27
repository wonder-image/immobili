<?php

/**
 * Lista immobili in vendita/affitto: filtri (GET) + mappa + griglia + paginazione.
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;

$PAGE_KEY = 'immobili.list';

$SEO->title = __t('pages.immobili.list.seo.title');
$SEO->description = __t('pages.immobili.list.seo.description');
$SEO->url = __r($PAGE_KEY);
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    $SEO->url => __t('components.navigation.immobili'),
];

$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

$state = Immobili::context();
$filters = is_array($state['filters'] ?? null) ? $state['filters'] : [];
$perPage = (int) ($state['per_page'] ?? 12);

$query = new ImmobileQuery();

$where = $query->where($filters, false);
[$order, $direction] = $query->order((string) ($filters['ordina'] ?? 'recenti'));

$PAGINATION = pagination('immobili', $where, $perPage);
$rows = Immobile::safeFind( $where, $PAGINATION->limit, $order, $direction);

$items = CardViewModel::fromImmobili($query->cards($rows));
$total = (int) sqlCount('immobili', $where);
$geojson = $query->geojson($where);

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">

        <h1 class="title-big"><?= e(__t('pages.immobili.list.title')) ?></h1>

        <div class="mt-4">
            <?php Immobili::component('immobili/filters', [ 'filters' => $filters, 'action' => __r('immobili.list') ]); ?>
        </div>

    </div>
</section>

<?php if (!empty($geojson)) { ?>
<section>
    <div class="content">
        <?php Immobili::component('map', [ 'features' => $geojson, 'markerMode' => 'icon' ]); ?>
    </div>
</section>
<?php } ?>

<section>
    <div class="content">

        <p class="text tx-muted"><?= $total ?> <?= e(__t('pages.immobili.list.results')) ?></p>

        <?php if ($items === []) { ?>
            <p class="text mt-4"><?= e(__t('pages.immobili.list.empty')) ?></p>
        <?php } else { ?>
            <?php Immobili::component('cards', [
                'items' => $items,
                'class' => 'mt-4',
            ]); ?>
        <?php } ?>

        <div class="w-100 d-flex j-content-center mt-8">
            <?= $PAGINATION->html ?>
        </div>

    </div>
</section>

<?php \Wonder\View\View::end(); ?>
