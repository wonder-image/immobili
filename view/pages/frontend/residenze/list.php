<?php

/**
 * Lista residenze/cantieri: griglia di card ordinate per position.
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;

$PAGE_KEY = 'residenze.list';

$SEO->title = __t('pages.residenze.list.seo.title');
$SEO->description = __t('pages.residenze.list.seo.description');
$SEO->url = __r($PAGE_KEY);
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    $SEO->url => __t('pages.residenze.list.title'),
];

$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

$rows = Residenza::safeFind(['visible' => 'true', 'deleted' => 'false'], null, 'position', 'ASC');
$rows = is_array($rows) && isset($rows['id']) ? [$rows] : (is_array($rows) ? $rows : []);
$presenter = new ResidenzaPresenter();

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">
        <h1 class="title-big"><?= e(__t('pages.residenze.list.title')) ?></h1>
    </div>
</section>

<section>
    <div class="content">
        <?php if ($rows === []) { ?>
            <p class="text mt-4"><?= e(__t('pages.residenze.list.empty')) ?></p>
        <?php } else { ?>
            <div class="d-grid col-3 col-p-1 gap-5 mt-4">
                <?php foreach ($rows as $row) {
                    Immobili::component('residenze/card', ['residenza' => $row, 'presenter' => $presenter]);
                } ?>
            </div>
        <?php } ?>
    </div>
</section>

<?php \Wonder\View\View::end(); ?>
