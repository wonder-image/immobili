<?php

/**
 * Dettaglio residenza: hero gallery (__swiper), descrizione, timeline, features,
 * unità, classe energetica, capitolato, immobili collegati, mappa, link sito esterno.
 */

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Support\EnergyScale;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;
use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Residenza::safeFind(['slug' => $slug, 'visible' => 'true', 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    header('Location: '.__r('residenze.list'), true, 302);
    exit;
}

$presenter = new ResidenzaPresenter();

// __swiper() vuole una mappa [src => alt]; ResidenzaPresenter::images() torna
// una lista di {src, alt} (stesso pattern di ImmobilePresenter::imagesAlt).
$slides = [];
foreach ($presenter->images($row) as $image) {
    $slides[(string) $image['src']] = (string) $image['alt'];
}

$features = is_array($row['features'] ?? null) ? $row['features'] : [];

$nome = (string) ($row['nome'] ?? '');
$sitoUrl = (string) ($row['sito_url'] ?? '');
$stato = ResidenzaPresenter::stato($row);
$statoLabel = (string) __t('pages.residenze.stato.'.$stato);

$inizio = ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0));
$fine = ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0));

$logoFile = ResidenzaPresenter::firstFile($row['logo'] ?? '');
$logoUrl = $logoFile !== '' ? ResidenzaPresenter::imageUrl($logoFile) : '';

$capitolatoFile = ResidenzaPresenter::firstFile($row['capitolato'] ?? '');
$capitolatoUrl = $capitolatoFile !== '' ? ResidenzaPresenter::imageUrl($capitolatoFile) : '';

// Classe energetica: la residenza dichiara solo la classe (niente IPE né legge),
// la scala la deduce da quella. null se il campo è vuoto → il badge non esce.
$energyScale = EnergyScale::make((string) ($row['classe_energetica'] ?? ''), '', '');

// Immobili collegati (visibili) via FK.
$linkedRows = Immobile::safeFind(['residenza_id' => (int) $row['id'], 'visible' => 'true', 'deleted' => 'false'], null, 'creation', 'DESC');
$linkedRows = is_array($linkedRows) ? $linkedRows : [];
$linkedItems = (new ImmobileQuery())->cards($linkedRows);

// Mappa (se coordinate presenti).
$lat = trim((string) ($row['latitudine'] ?? ''));
$lon = trim((string) ($row['longitudine'] ?? ''));
$geojson = ($lat !== '' && $lon !== '')
    ? [[
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [(float) $lon, (float) $lat]],
        'properties' => ['title' => $nome],
    ]]
    : [];

$PAGE_KEY = 'residenze.detail';
$SEO->title = $nome.' - '.$SOCIETY->name;
$SEO->description = mb_substr(strip_tags((string) ($row['descrizione_breve'] ?? $nome)), 0, 160);
$SEO->url = __r('residenze.detail', ['slug' => $slug]);
$SEO->image = $presenter->cover($row);
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    __r('residenze.list') => __t('pages.residenze.list.title'),
    $SEO->url => $nome,
];
$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

if ($slides !== []) {
    Dependencies::swiper();
    Dependencies::fancyapps();
}

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">

        <div class="w-100">
            <a href="<?= e(__r('residenze.list')) ?>" class="text-small"><i class="bi bi-arrow-left"></i> <?= e(__t('pages.residenze.detail.back')) ?></a>
        </div>

        <?php if ($logoUrl !== '') { ?>
            <img src="<?= e($logoUrl) ?>" alt="<?= e($nome) ?>" class="w-15 w-p-30 mt-3">
        <?php } ?>

        <h1 class="title-big mt-3"><?= e($nome) ?></h1>

        <?php if (($row['comune_nome'] ?? '') !== '') { ?>
            <p class="text tx-muted mt-1"><i class="bi bi-geo-alt"></i> <?= e((string) $row['comune_nome']) ?><?php if (($row['indirizzo'] ?? '') !== '') { echo ', '.e((string) $row['indirizzo']); } ?></p>
        <?php } ?>

        <div class="mt-3">
            <?php Immobili::component('residenze/timeline', ['inizio' => $inizio, 'fine' => $fine, 'stato' => $statoLabel]); ?>
        </div>

    </div>
</section>

<?php if ($slides !== []) { ?>
<section class="pt-0">
    <div class="content">
        <div class="w-100 o-hidden">
            <?= __swiper($slides)->id('residenza-swiper')
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

                <?php if (($row['descrizione_lunga'] ?? '') !== '') { ?>
                    <div class="text"><?= nl2br(e((string) $row['descrizione_lunga'])) ?></div>
                <?php } ?>

                <?php if ($features !== []) { ?>
                    <h2 class="subtitle mt-6"><?= e(__t('forms.residenze.sections.features')) ?></h2>
                    <div class="mt-3"><?php Immobili::component('amenities', ['features' => $features]); ?></div>
                <?php } ?>

            </div>

            <aside class="d-grid gap-4">

                <?php if ((int) ($row['unita_abitative'] ?? 0) > 0) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.units')) ?></div>
                        <div class="title"><?= (int) $row['unita_abitative'] ?></div>
                    </div>
                <?php } ?>

                <?php if ($energyScale !== null) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.energy')) ?></div>
                        <div class="mt-2"><?php Immobili::component('energy-class/badge', ['scale' => $energyScale]); ?></div>
                    </div>
                <?php } ?>

                <?php if ($capitolatoUrl !== '') { ?>
                    <a href="<?= e($capitolatoUrl) ?>" target="_blank" rel="noopener" class="btn btn-dark w-100"><i class="bi bi-file-earmark-pdf"></i> <?= e(__t('pages.residenze.detail.download_capitolato')) ?></a>
                <?php } ?>

                <?php if ($sitoUrl !== '') { ?>
                    <a href="<?= e($sitoUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary w-100"><i class="bi bi-box-arrow-up-right"></i> <?= e(__t('pages.residenze.detail.visit_site')) ?></a>
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

<?php if ($geojson !== []) { ?>
<section class="pt-0">
    <div class="content">
        <?php Immobili::component('map', ['features' => $geojson, 'markerMode' => 'icon']); ?>
    </div>
</section>
<?php } ?>

<?php \Wonder\View\View::end(); ?>
