<?php

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;
use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Residenza::safeFind(['slug' => $slug, 'visible' => 'true', 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    header('Location: '.__r('residenze.list'), true, 302);
    exit;
}

$residenza = (new ResidenzaPresenter())->present($row);

$linkedRows = Immobile::safeFind(['residenza_id' => (int) $row['id'], 'visible' => 'true', 'deleted' => 'false'], null, 'creation', 'DESC');
$linkedRows = is_array($linkedRows) ? $linkedRows : [];
$linkedItems = (new ImmobileQuery())->cards($linkedRows);

$PAGE_KEY = 'residenze.detail';
$GLOBALS['PAGE_KEY'] = $PAGE_KEY;
$SEO->title = $residenza->nome.' - '.$SOCIETY->name;
$SEO->description = mb_substr(strip_tags($residenza->descrizione_breve), 0, 160);
$SEO->url = $residenza->url;
$SEO->image = $residenza->cover;
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    __r('residenze.list') => __t('pages.residenze.list.title'),
    $SEO->url => $residenza->nome,
];

Dependencies::swiper();
Dependencies::fancyapps();

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">

        <div class="w-100">
            <a href="<?= e(__r('residenze.list')) ?>" class="text-small"><i class="bi bi-arrow-left"></i> <?= e(__t('pages.residenze.detail.back')) ?></a>
        </div>

        <?php if ($residenza->logoUrl !== '') { ?>
            <img src="<?= e($residenza->logoUrl) ?>" alt="<?= e($residenza->nome) ?>" class="w-15 w-p-30 mt-3">
        <?php } ?>

        <h1 class="title-big mt-3"><?= e($residenza->nome) ?></h1>

        <?php if ($residenza->prettyAddress !== '') { ?>
            <p class="text tx-muted mt-1"><i class="bi bi-geo-alt"></i> <?= e($residenza->prettyAddress) ?></p>
        <?php } ?>

        <div class="mt-3">
            <?php Immobili::component('residenze/timeline', [
                'inizio' => $residenza->inizio,
                'fine' => $residenza->fine,
                'stato' => (string) __t('pages.residenze.stato.'.$residenza->stato),
            ]); ?>
        </div>

    </div>
</section>

<?php if ($residenza->imagesAlt !== []) { ?>
<section class="pt-0">
    <div class="content">
        <div class="w-100 o-hidden">
            <?= __swiper($residenza->imagesAlt)->id('residenza-swiper')
                    ->ratio('3:2')
                    ->thumbnails()
                    ->thumbsRatio('3:2')
                    ->lightbox()
                    ->navigation() ?>
        </div>
    </div>
</section>
<?php } ?>

<section class="pt-0">
    <div class="content">
        <div class="w-100 d-grid col-3 col-p-1 gap-8">

            <div class="col-2 col-p-1">

                <?php if ($residenza->descrizione_lunga !== '') { ?>
                    <div class="text"><?= nl2br(e($residenza->descrizione_lunga)) ?></div>
                <?php } ?>

                <?php if ($residenza->features !== []) { ?>
                    <h2 class="subtitle mt-6"><?= e(__t('forms.residenze.sections.features')) ?></h2>
                    <div class="mt-3"><?php Immobili::component('amenities', ['features' => $residenza->features]); ?></div>
                <?php } ?>

            </div>

            <aside class="d-grid gap-4">

                <?php if ($residenza->unita_abitative > 0) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.units')) ?></div>
                        <div class="title"><?= $residenza->unita_abitative ?></div>
                    </div>
                <?php } ?>

                <?php if ($residenza->unita_commerciali > 0) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.commercial_units')) ?></div>
                        <div class="title"><?= $residenza->unita_commerciali ?></div>
                    </div>
                <?php } ?>

                <?php if ($residenza->box > 0) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.boxes')) ?></div>
                        <div class="title"><?= $residenza->box ?></div>
                    </div>
                <?php } ?>

                <?php if ($residenza->energyScale !== null) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.energy')) ?></div>
                        <div class="mt-2"><?php Immobili::component('energy-class/badge', ['scale' => $residenza->energyScale]); ?></div>
                    </div>
                <?php } ?>

                <?php if ($residenza->capitolatoUrl !== '') { ?>
                    <a href="<?= e($residenza->capitolatoUrl) ?>" target="_blank" rel="noopener" class="btn btn-dark w-100"><i class="bi bi-file-earmark-pdf"></i> <?= e(__t('pages.residenze.detail.download_capitolato')) ?></a>
                <?php } ?>

                <?php if ($residenza->sito_url !== '') { ?>
                    <a href="<?= e($residenza->sito_url) ?>" target="_blank" rel="noopener" class="btn btn-primary w-100"><i class="bi bi-box-arrow-up-right"></i> <?= e(__t('pages.residenze.detail.visit_site')) ?></a>
                <?php } ?>

            </aside>

        </div>
    </div>
</section>

<?php if ($linkedItems !== []) { ?>
<section class="pt-0">
    <div class="content">
        <h2 class="subtitle"><?= e(__t('pages.residenze.detail.linked')) ?></h2>
        <?php Immobili::component('immobili/cards-grid', [
            'immobili' => $linkedItems,
            'class' => 'mt-4',
        ]); ?>
    </div>
</section>
<?php } ?>

<?php if ($residenza->geo_json !== []) { ?>
<section class="pt-0">
    <div class="content">
        <?php Immobili::component('map', ['features' => [$residenza->geo_json], 'markerMode' => 'icon']); ?>
    </div>
</section>
<?php } ?>

<?php \Wonder\View\View::end(); ?>
