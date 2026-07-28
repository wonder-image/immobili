<?php

use Wonder\App\Dependencies;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Media\Iframe;
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

// Le colonne media sono gia normalizzate e validate da Immobile::decorate().
$videos = array_values(array_unique(array_merge(
    $row['youtube'] ?? [],
    $row['video'] ?? []
)));
$virtualTours = $row['virtual_tour'] ?? [];

$iframes = static fn (array $urls): array => array_map(
    static fn (string $url): Iframe => Iframe::url($url)
        ->attr('allowfullscreen', true)
        ->class('w-100')
        ->style('aspect-ratio', '16 / 9')
        ->style('display', 'block'),
    $urls
);

$mediaGrid = static fn (array $urls): Container => (new Container())
    ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
    ->gap(3)
    ->components($iframes($urls));

Dependencies::swiper();
Dependencies::fancyapps();

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
                
                <div class="w-100 o-hidden">
                    <?= __swiper($slides)->id('immobile-swiper')
                            ->ratio('3:2')
                            ->thumbnails()
                            ->thumbsRatio('3:2')
                            ->lightbox()
                            ->navigation() ?>
                </div>

                <?php if ($planSlides !== []) { ?>
                    <h2 class="subtitle mt-8"><?= e(__t('pages.immobili.detail.plans')) ?></h2>
                    <div class="mt-3"><?= __gallery($planSlides)->columns(2, 2, 1)->format('4-3') ?></div>
                <?php } ?>

            </div>

            <aside>

                <?php if (($immobile->prezzo ?? '') !== '') { ?>
                    <div class="title-big"><?= e($immobile->prettyPrezzo) ?></div>
                <?php } ?>

                <div class="mt-6">
                    <a class="btn btn-primary" href="<?= e($immobile->url_scheda) ?>">
                        <i class="bi bi-file-earmark-pdf"></i> <?= e(__t('pages.immobili.detail.pdf')) ?>
                    </a>
                </div>

            </aside>

        </div>
    </div>
</section>

<section class="pt-0">
    <div class="content">

        <?php Immobili::component('features', ['immobile' => $immobile]); ?>

    </div>
</section>

<section class="pt-0">
    <div class="content">

        <div class="w-100 d-grid col-1 gap-5">

            <?php
                if (($immobile->descrizione ?? '') !== '') {
                    echo Accordion::make(__t('pages.immobili.detail.description'))
                        ->description($immobile->descrizione)
                        ->descriptionSize('text-small')
                        ->icon('plus');
                }
            ?>

            <?php if ($videos !== []) { ?>
                <?= Accordion::make(__t('pages.immobili.detail.video'))
                    ->components([$mediaGrid($videos)])
                    ->icon('plus') ?>
            <?php } ?>

            <?php if ($virtualTours !== []) { ?>
                <?= Accordion::make(__t('pages.immobili.detail.virtual_tour'))
                    ->components([$mediaGrid($virtualTours)])
                    ->icon('plus') ?>
            <?php } ?>
        </div>

    </div>
</section>

<?php if (!empty($immobile->geo_json)) { ?>
<section class="pt-0">
    <div class="content">

        <?php Immobili::component('map', [ 'features' => [ $immobile->geo_json ], 'zoom' => 15 ]); ?>

    </div>
</section>
<?php } ?>

<section>
    <div class="content">
        <?= __gallery($slides) ?>
    </div>
</section>

<?php \Wonder\View\View::end(); ?>
