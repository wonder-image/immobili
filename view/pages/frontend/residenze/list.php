<?php

/**
 * Lista residenze/cantieri: griglia di card ordinate per position.
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;
use Wonder\Plugin\Immobili\Catalog\ResidenzaQuery;

$PAGE_KEY = 'residenze.list';

$SEO->title = __t('pages.residenze.list.seo.title');
$SEO->description = __t('pages.residenze.list.seo.description');
$SEO->url = __r($PAGE_KEY);
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    $SEO->url => __t('pages.residenze.list.title'),
];

$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

$presenter = new ResidenzaPresenter();
$query = new ResidenzaQuery($presenter);
$filters = $query->filters($_GET);
$where = $query->where($filters);
$rows = Residenza::safeFind($where, null, 'position', 'ASC');
$rows = is_array($rows) && isset($rows['id']) ? [$rows] : (is_array($rows) ? $rows : []);
$geojson = $query->geojson($where);

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">
        <h1 class="title-big"><?= e(__t('pages.residenze.list.title')) ?></h1>
        <div class="mt-4">
            <?php Immobili::component('residenze/filters', ['filters' => $filters, 'action' => __r('residenze.list')]); ?>
        </div>
    </div>
</section>

<?php if (!empty($geojson)) { ?>
<section>
    <div class="content">
        <?php Immobili::component('map', ['features' => $geojson, 'markerMode' => 'icon']); ?>
    </div>
</section>
<?php } ?>

<section>
    <div class="content">
        <?php if ($rows === []) { ?>
            <p class="text mt-4"><?= e(__t('pages.residenze.list.empty')) ?></p>
        <?php } else { ?>
            <?php Immobili::component('residenze/cards-grid', [
                'residenze' => $rows,
                'presenter' => $presenter,
                'class' => 'mt-4',
            ]); ?>
        <?php } ?>
    </div>
</section>

<?php \Wonder\View\View::end(); ?>
