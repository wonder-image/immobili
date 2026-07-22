<?php

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Immobile::safeFind(['slug' => $slug, 'visible' => 'true', 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    header('Location: '.__r('immobili.list'), true, 302);
    exit;
}

$immobile = (new ImmobilePresenter())->present($row);

$PAGE_KEY = 'immobili.detail';
$SEO->title = $immobile->titolo.' - '.$SOCIETY->name;
$SEO->description = mb_substr(strip_tags((string) ($immobile->descrizione ?: $immobile->prettyName)), 0, 160);
$SEO->url = (string) $immobile->url;
$SEO->image = $immobile->image;
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    __r('immobili.list') => __t('components.navigation.immobili'),
    $SEO->url => $immobile->titolo
];


$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

$videos = array_values(array_filter([
    (string) ($row['youtube_1'] ?? ''),
    (string) ($row['youtube_2'] ?? ''),
    (string) ($row['youtube_3'] ?? ''),
    (string) ($row['youtube_4'] ?? ''),
], static fn (string $v): bool => $v !== ''));

Dependencies::swiper();
Dependencies::fancyapps();

// I builder del framework accettano la forma ['sorgente' => 'alt/caption'].
$slides = is_array($immobile->imagesAlt ?? null) ? $immobile->imagesAlt : [];
$planSlides = is_array($immobile->planimetrieAlt ?? null) ? $immobile->planimetrieAlt : [];

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">

        <div class="w-100">
            <a href="<?= e(__r('immobili.list')) ?>" class="text-small"><i class="bi bi-arrow-left"></i> <?= e(__t('pages.immobili.detail.back')) ?></a>
        </div>

        <h1 class="p-r f-start w-100 title-big mt-3"><?= e($immobile->titolo) ?></h1>

        <?php if (($immobile->prettyAddress ?? '') !== '') { ?>
            <p class="p-r f-start w-100 text tx-muted mt-1"><i class="bi bi-geo-alt"></i> <?= e($immobile->prettyAddress) ?></p>
        <?php } ?>

    </div>
</section>

<section class="pt-0">
    <div class="content">
        <div class="w-100 d-grid col-3 col-p-1 gap-8">
            
            <div class="col-2 col-p-1">
                
                <style>
                    #immobile-swiper .swiper-slide{aspect-ratio:16/9}
                    #immobile-swiper-thumbs .swiper-slide{aspect-ratio:4/3;cursor:pointer;opacity:.55;transition:opacity .2s}
                    #immobile-swiper-thumbs .swiper-slide-thumb-active{opacity:1}
                </style>
                <div class="w-100 b-r-10 o-hidden">
                    <?= __swiper($slides)->id('immobile-swiper')->thumbnails()->lightbox()->navigation() ?>
                </div>

                <?php if ($planSlides !== []) { ?>
                    <h2 class="subtitle mt-8"><?= e(__t('pages.immobili.detail.plans')) ?></h2>
                    <div class="mt-3"><?= __gallery($planSlides)->columns(2, 2, 1)->format('4-3') ?></div>
                <?php } ?>

                <?php if (($immobile->descrizione ?? '') !== '') { ?>
                    <h2 class="subtitle mt-8"><?= e(__t('pages.immobili.detail.description')) ?></h2>
                    <div class="text mt-3"><?= nl2br(e($immobile->descrizione)) ?></div>
                <?php } ?>

                <?php foreach ($videos as $video) { ?>
                    <div class="mt-6" style="position:relative;aspect-ratio:16/9;">
                        <iframe src="<?= e($video) ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0;border-radius:10px;" allowfullscreen loading="lazy"></iframe>
                    </div>
                <?php } ?>
            </div>

            <aside>
                <?php if (($immobile->prezzo ?? '') !== '') { ?>
                    <div class="title-big"><?= e($immobile->prezzo) ?></div>
                <?php } ?>

                <div class="mt-6">
                    <a class="btn btn-primary" href="<?= e($immobile->url_scheda) ?>">
                        <i class="bi bi-file-earmark-pdf"></i> <?= e(__t('pages.immobili.detail.pdf')) ?>
                    </a>
                </div>

                <?php if (!empty($immobile->geo_json)) { ?>
                    <div class="mt-6">
                        <?php Immobili::component('map', ['features' => [$immobile->geo_json], 'zoom' => 15]); ?>
                    </div>
                <?php } ?>
            </aside>

        </div>
    </div>
</section>

<section class="pt-0">
    <div class="content">

        <?php Immobili::component('features', ['immobile' => $immobile]); ?>

    </div>
</section>

<section>
    <div class="content">
        <?= __gallery($slides) ?>
    </div>
</section>

<?php \Wonder\View\View::end(); ?>
