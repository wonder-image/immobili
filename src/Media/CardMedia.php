<?php

namespace Wonder\Plugin\Immobili\Media;

use Wonder\App\Dependencies;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Media\Swiper;
use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;

final class CardMedia
{
    public static function immobile(object $immobile, array $options = []): Container|Swiper
    {
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

        return self::make($images, trim((string) ($immobile->cover ?? '')), $alt, $options);
    }

    public static function residenza(
        array $residenza,
        array $options = [],
        ?ResidenzaPresenter $presenter = null
    ): Container|Swiper {
        $presenter ??= new ResidenzaPresenter();
        $alt = trim((string) ($residenza['nome'] ?? ''));
        $images = [];

        foreach ($presenter->images($residenza) as $image) {
            $src = trim((string) ($image['src'] ?? ''));

            if ($src !== '') {
                $images[$src] = trim((string) ($image['alt'] ?? $alt));
            }
        }

        return self::make($images, trim($presenter->cover($residenza)), $alt, $options);
    }

    /** @param array<string, string> $images */
    public static function make(array $images, string $cover = '', string $alt = '', array $options = []): Container|Swiper
    {
        $ratio = trim((string) ($options['ratio'] ?? '3:2')) ?: '3:2';
        $imageClass = $options['image_class'] ?? [];
        $imageClass = is_array($imageClass) ? implode(' ', $imageClass) : $imageClass;

        if (($options['gallery'] ?? false) && count($images) > 1) {
            Dependencies::swiper();

            $swiper = __swiper($images)
                ->ratio($ratio)
                ->keyboard()
                ->watchOverflow()
                ->navigation()
                ->slideClass($options['slide_class'] ?? []);

            if (trim($imageClass) !== '') {
                $slides = [];

                foreach ($images as $src => $imageAlt) {
                    $image = __ri($src)->alt($imageAlt)->fitCover()->size(1440)->addClass($imageClass);
                    $slides[] = (new Container())
                        ->ratio($ratio)
                        ->addClass('o-hidden w-100')
                        ->components([$image]);
                }

                $swiper->slides($slides);
            }

            return $swiper;
        }

        $media = (new Container())->ratio($ratio)->addClass('o-hidden w-100');
        $src = $cover !== '' ? $cover : (string) (array_key_first($images) ?? '');

        if ($src !== '') {
            $image = __ri($src)->alt($images[$src] ?? $alt)->fitCover();

            if (trim($imageClass) !== '') {
                $image->addClass($imageClass);
            }

            if ($cover !== '') {
                $image->sizes([])->hasWebP(false);
            }

            $media->components([$image]);
        }

        return $media;
    }
}
