<?php

/**
 * Card immobile base: immagine sopra e dati su fondo chiaro.
 *
 * @var array $args ['immobile' => object, 'gallery' => bool, 'ratio' => string, 'slide_class' => string|string[]]
 */

use Wonder\App\Dependencies;
use Wonder\Elements\Components\Container;

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

$url = (string) ($immobile->url ?? '#');
$tipologia = trim((string) ($immobile->tipologia ?? ''));
$contratto = trim((string) ($immobile->contratto ?? ''));
$eyebrow = $tipologia !== ''
    ? $tipologia.($contratto !== '' ? ' · '.$contratto : '')
    : '';
$prezzo = trim((string) ($immobile->prezzo ?? '')) !== ''
    ? (string) ($immobile->prettyPrezzo ?? '')
    : '';
$superficie = trim((string) ($immobile->prettySuperficie ?? ''));
$alt = trim((string) ($immobile->prettyName ?? ''));
$imageAlts = is_array($immobile->imagesAlt ?? null) ? $immobile->imagesAlt : [];
$images = [];

foreach (is_array($immobile->images ?? null) ? $immobile->images : [] as $key => $image) {
    $src = '';
    $imageAlt = $alt;

    if (is_string($key)) {
        $src = trim($key);
        $imageAlt = is_scalar($image) ? trim((string) $image) : $alt;
    } elseif (is_string($image)) {
        $src = trim($image);
        $imageAlt = trim((string) ($imageAlts[$src] ?? $alt));
    } elseif (is_array($image)) {
        $src = trim((string) ($image['src'] ?? ''));
        $imageAlt = trim((string) ($image['alt'] ?? $imageAlts[$src] ?? $alt));
    } elseif (is_object($image)) {
        $src = trim((string) ($image->src ?? ''));
        $imageAlt = trim((string) ($image->alt ?? $imageAlts[$src] ?? $alt));
    }

    if ($src !== '') {
        $images[$src] = $imageAlt;
    }
}

$cover = trim((string) ($immobile->cover ?? ''));
$useSwiper = (bool) ($args['gallery'] ?? false) && count($images) > 1;
$singleSrc = $cover !== '' ? $cover : (string) (array_key_first($images) ?? '');
$singleAlt = $singleSrc !== '' ? (string) ($images[$singleSrc] ?? $alt) : '';
$ratio = trim((string) ($args['ratio'] ?? '3:2')) ?: '3:2';

if ($useSwiper) {
    Dependencies::swiper();
}

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($url) ?>">
    <div class="p-r o-hidden">
        <?php if ($useSwiper) {
            $swiper = __swiper($images)
                ->ratio($ratio)
                ->keyboard()
                ->watchOverflow()
                ->navigation();

            $slideClass = $args['slide_class'] ?? [];
            if ((is_string($slideClass) && trim($slideClass) !== '') || (is_array($slideClass) && $slideClass !== [])) {
                $swiper->slideClass($slideClass);
            }

            echo $swiper->render('wonder');
        } else {
            $media = (new Container())->ratio($ratio)->addClass('o-hidden');

            if ($singleSrc !== '') {
                $image = __ri($singleSrc)->alt($singleAlt)->fitCover();

                if ($cover !== '') {
                    $image->sizes([])->hasWebP(false);
                }

                $media->components([$image]);
            }

            echo $media->render('wonder');
        } ?>
        <?php if (!empty($immobile->sold)) { ?>
            <span class="p-a top start badge badge-danger tx-upper m-3"><?= e(__t('components.immobili.card.sold')) ?></span>
        <?php } elseif (!empty($immobile->evidence)) { ?>
            <span class="p-a top start badge badge-dark tx-upper m-3"><?= e(__t('components.immobili.card.featured')) ?></span>
        <?php } ?>
    </div>
    <div class="p-4 d-grid gap-2">
        <?php if ($eyebrow !== '') { ?>
            <div class="text-small tx-upper tx-muted"><?= e($eyebrow) ?></div>
        <?php } ?>
        <div class="text fw-600"><?= e((string) ($immobile->prettyName ?? '')) ?></div>
        <?php if (trim((string) ($immobile->prettyAddress ?? '')) !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e((string) $immobile->prettyAddress) ?></div>
        <?php } ?>
        <?php if ($prezzo !== '') { ?>
            <div class="text fw-700 tx-primary"><?= e($prezzo) ?></div>
        <?php } ?>
        <div class="d-flex gap-4 text-small tx-muted mt-1">
            <?php if ($superficie !== '') { ?>
                <span><i class="bi bi-rulers"></i> <?= e($superficie) ?></span>
            <?php } ?>
            <?php if ((int) ($immobile->locali ?? 0) > 0) { ?>
                <span><i class="bi bi-door-open"></i> <?= (int) $immobile->locali ?> <?= e(__t('components.immobili.card.rooms')) ?></span>
            <?php } ?>
            <?php if ((int) ($immobile->camere ?? 0) > 0) { ?>
                <span><i class="bi bi-house"></i> <?= (int) $immobile->camere ?></span>
            <?php } ?>
            <?php if ((int) ($immobile->bagni ?? 0) > 0) { ?>
                <span><i class="bi bi-droplet"></i> <?= (int) $immobile->bagni ?></span>
            <?php } ?>
        </div>
    </div>
</a>
