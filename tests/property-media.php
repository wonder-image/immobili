<?php

// php tests/property-media.php [site-root] — no database or network required.
$module = dirname(__DIR__);
$site = $argv[1] ?? null;
require ($site ?: $module).'/vendor/autoload.php';
define('APP_URL', 'https://example.test');
define('ROOT', $site ?: $module);
define('ASSETS_VERSION', 'test');
define('APP_VERSION', 'test');
define('RESPONSIVE_IMAGE_SIZES', [240, 480, 620, 960, 1200, 1440, 1920, 2400]);
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

use Wonder\Elements\Media\Swiper;
use Wonder\Elements\Media\Gallery;

$check = static function (bool $result, string $message): void {
    if (!$result) { throw new RuntimeException($message); }
};
$photos = ['https://example.test/photos/house.jpg' => 'House', 'https://images.example.org/feed.jpg?size=full' => 'External'];
$html = Swiper::make($photos)->id('test-property')->thumbnails()->lightbox()->priority()
    ->imageSizes('(max-width: 768px) 100vw, 66vw')
    ->thumbsImageSizes('(max-width: 768px) 25vw, 17vw')->render('wonder');
$check(substr_count($html, 'fetchpriority="high"') === 1, 'Only the hero gets high priority');
$check(substr_count($html, 'loading="eager"') === 1, 'Only the hero loads eagerly');
$check(str_contains($html, 'sizes="(max-width: 768px) 25vw, 17vw"'), 'Thumbnail sizes match layout');
$check(str_contains($html, 'house-240.webp'), 'Generated thumbnails have WebP variants');
$check(str_contains($html, 'src="https://images.example.org/feed.jpg?size=full"'), 'External URLs preserved');
$check(!str_contains($html, 'feed-'), 'No invented remote variants');
$check(str_contains($html, 'data-fancybox-trigger='), 'Lightbox preserved');
$html = Gallery::make($photos)->render('wonder');
$check(!str_contains($html, 'fetchpriority="high"'), 'Lower gallery is not prioritized');
$check(str_contains($html, 'sizes="(max-width: 768px) 50vw, (max-width: 992px) 33.333333333333vw, 25vw"'), 'Gallery sizes match columns');

$html = \Wonder\Elements\Media\Iframe::url('https://example.org/tour')
    ->deferred(button: \Wonder\Elements\Components\Button::make('Open tour'))->render('wonder');
$check(str_contains($html, '<template data-wi-deferred-template><iframe'), 'Iframe stays in inert template');
$check(str_contains($html, 'href="https://example.org/tour"'), 'No-JS fallback is a real link');
try {
    \Wonder\Elements\Media\Deferred::make('test')->fallbackUrl('javascript:alert(1)');
    throw new RuntimeException('Unsafe URL was accepted');
} catch (InvalidArgumentException) {
}
echo "Property media checks passed\n";
