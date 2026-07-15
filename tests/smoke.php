<?php

declare(strict_types=1);

/**
 * Smoke test del modulo Immobili.
 *
 * Verifica in isolamento gli helper puri (senza runtime del framework).
 * Esecuzione:  php tests/smoke.php
 */

require __DIR__.'/../src/helpers.php';

$failures = 0;
$total = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$total): void {
    $total++;
    if ($condition) {
        echo "  ✓ {$message}\n";
        return;
    }
    $failures++;
    echo "  ✗ {$message}\n";
};

echo "immobiliIsTrue\n";
$assert(immobiliIsTrue('true'), "'true' => true");
$assert(immobiliIsTrue('1'), "'1' => true");
$assert(immobiliIsTrue('Sì'), "'Sì' => true");
$assert(!immobiliIsTrue('false'), "'false' => false");
$assert(!immobiliIsTrue(''), "'' => false");

echo "immobiliDecodeJsonArray\n";
$assert(immobiliDecodeJsonArray('[1,2,3]') === [1, 2, 3], "JSON valido => array");
$assert(immobiliDecodeJsonArray('') === [], "vuoto => []");
$assert(immobiliDecodeJsonArray('nope') === [], "non-JSON => []");
$assert(immobiliDecodeJsonArray(['a']) === ['a'], "array passthrough");

echo "immobiliSlug\n";
$assert(immobiliSlug('Villa a Città Alta') === 'villa-a-citta-alta', "translittera e slugghifica");
$assert(immobiliSlug('  Trilocale, Via Roma 10  ') === 'trilocale-via-roma-10', "trim + separatori");
$assert(immobiliSlug('') === '', "vuoto => vuoto");

echo "immobiliFormatPrice\n";
$assert(immobiliFormatPrice(250000) === '€ 250.000', "prezzo formattato");
$assert(immobiliFormatPrice(0) === '', "0 => vuoto");
$assert(immobiliFormatPrice('1500') === '€ 1.500', "stringa numerica");

echo "immobiliFormatSurface\n";
$assert(immobiliFormatSurface(120) === '120 mq', "superficie formattata");
$assert(immobiliFormatSurface(0) === '', "0 => vuoto");

echo "immobiliResolveLocalizedValue\n";
$resolved = immobiliResolveLocalizedValue(['titolo' => ['it' => 'Casa', 'en' => 'House']], 'en');
$assert(($resolved['titolo'] ?? '') === 'House', "estrae variante en");
$resolved = immobiliResolveLocalizedValue(['titolo' => ['it' => 'Casa', 'en' => 'House']], 'fr');
$assert(($resolved['titolo'] ?? '') === 'Casa', "fallback it per lingua mancante");
$resolved = immobiliResolveLocalizedValue(['x' => 'plain'], 'en');
$assert(($resolved['x'] ?? '') === 'plain', "valore non localizzato passthrough");

// Autoloader delle classi del modulo (PSR-4, senza framework) per validare che
// il grafo delle classi si carichi senza errori di namespace/path/interfacce.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Wonder\\Plugin\\Immobili\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__.'/../src/'.$rel.'.php';
    if (is_file($file)) {
        require $file;
    }
});

echo "NormalizedListing\n";
$listing = new \Wonder\Plugin\Immobili\Feed\NormalizedListing('ABC');
$listing->set('prezzo', '100')
    ->attribute('comune', 'Milano')
    ->addImage(['url' => 'x'])
    ->addDescription(['lingua' => 'it']);
$assert($listing->externalId === 'ABC', 'external id');
$assert(($listing->fields['prezzo'] ?? '') === '100', 'set campo canonico');
$assert(($listing->attributi['comune'] ?? '') === 'Milano', 'attributo esteso');
$assert(count($listing->images) === 1 && count($listing->descriptions) === 1, 'immagini/descrizioni');

echo "FeedSourceConfig\n";
$cfg = \Wonder\Plugin\Immobili\Feed\FeedSourceConfig::fromRow(['id' => '7', 'provider' => 'getrix', 'code' => 'X1']);
$assert($cfg->id === 7, 'cast id int');
$assert($cfg->provider === 'getrix', 'provider');
$assert($cfg->code === 'X1', 'code');

echo "ProviderRegistry\n";
$opts = \Wonder\Plugin\Immobili\Feed\ProviderRegistry::options();
$assert(($opts['getrix'] ?? '') === 'Getrix', 'provider getrix registrato');
$assert(($opts['gestim'] ?? '') === 'Gestim', 'provider gestim registrato');

echo "ImmobileQuery::order\n";
$Q = new \Wonder\Plugin\Immobili\Services\ImmobileQuery();
$assert($Q->order('recenti') === ['evidence DESC, id', 'DESC'], "recenti => id DESC");
$assert($Q->order('prezzo_asc') === ['evidence DESC, prezzo', 'ASC'], "prezzo_asc");
$assert($Q->order('prezzo_desc') === ['evidence DESC, prezzo', 'DESC'], "prezzo_desc");
$assert($Q->order('superficie_desc') === ['evidence DESC, superficie', 'DESC'], "superficie_desc");
$assert($Q->order('boh') === ['evidence DESC, id', 'DESC'], "default => recenti");

echo "\n";
echo $failures === 0
    ? "OK — {$total} asserzioni passate\n"
    : "FAIL — {$failures}/{$total} asserzioni fallite\n";

exit($failures === 0 ? 0 : 1);
