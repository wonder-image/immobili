<?php

declare(strict_types=1);

/**
 * Smoke test del modulo Immobili.
 *
 * Verifica in isolamento gli helper puri (senza runtime del framework).
 * Esecuzione:  php tests/smoke.php
 */

require __DIR__.'/../src/helpers.php';

// Autoloader PSR-4 delle classi del modulo (senza framework): attivo per
// l'intero file, così i formatter spostati in ImmobilePresenter (slug,
// formatSurface, price) sono risolvibili anche nei test più in alto.
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

echo "ImmobilePresenter::slug\n";
$assert(\Wonder\Plugin\Immobili\Services\ImmobilePresenter::slug('Villa a Città Alta') === 'villa-a-citta-alta', "translittera e slugghifica");
$assert(\Wonder\Plugin\Immobili\Services\ImmobilePresenter::slug('  Trilocale, Via Roma 10  ') === 'trilocale-via-roma-10', "trim + separatori");
$assert(\Wonder\Plugin\Immobili\Services\ImmobilePresenter::slug('') === '', "vuoto => vuoto");

echo "immobiliFormatPrice\n";
$assert(immobiliFormatPrice(250000) === '€ 250.000', "prezzo formattato");
$assert(immobiliFormatPrice(0) === '', "0 => vuoto");
$assert(immobiliFormatPrice('1500') === '€ 1.500', "stringa numerica");

echo "ImmobilePresenter::formatSurface\n";
$assert(\Wonder\Plugin\Immobili\Services\ImmobilePresenter::formatSurface(120) === '120 mq', "superficie formattata");
$assert(\Wonder\Plugin\Immobili\Services\ImmobilePresenter::formatSurface(0) === '', "0 => vuoto");

echo "immobiliResolveLocalizedValue\n";
$resolved = immobiliResolveLocalizedValue(['titolo' => ['it' => 'Casa', 'en' => 'House']], 'en');
$assert(($resolved['titolo'] ?? '') === 'House', "estrae variante en");
$resolved = immobiliResolveLocalizedValue(['titolo' => ['it' => 'Casa', 'en' => 'House']], 'fr');
$assert(($resolved['titolo'] ?? '') === 'Casa', "fallback it per lingua mancante");
$resolved = immobiliResolveLocalizedValue(['x' => 'plain'], 'en');
$assert(($resolved['x'] ?? '') === 'plain', "valore non localizzato passthrough");

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

echo "GetrixProvider\n";
$testRoot = sys_get_temp_dir().'/immobili-getrix-'.bin2hex(random_bytes(5));
$sourceRoot = $testRoot.'/source';
$archiveRoot = $testRoot.'/archive';
mkdir($sourceRoot, 0775, true);
$fixtureXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Getrix>
  <Immobile IDImmobile="ABC-123">
    <CodiceComune>001</CodiceComune>
    <Categoria>1</Categoria>
    <Contratto>V</Contratto>
    <Tipologia IDTipologia="10">Appartamento</Tipologia>
    <Prezzo>250000</Prezzo>
    <Riferimento>RIF 123</Riferimento>
    <DataInserimento>2026-01-01 10:00:00</DataInserimento>
    <DataModifica>2026-01-02 11:00:00</DataModifica>
  </Immobile>
</Getrix>
XML;
$fixtureZip = new ZipArchive();
$fixtureZip->open($sourceRoot.'/TEST.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
$fixtureZip->addFromString('TEST.xml', $fixtureXml);
$fixtureZip->close();

$getrix = new \Wonder\Plugin\Immobili\Feed\GetrixProvider('file://'.$sourceRoot.'/', $archiveRoot);
$getrixFeed = \Wonder\Plugin\Immobili\Feed\FeedSourceConfig::fromRow([
    'id'       => 9,
    'provider' => 'getrix',
    'code'     => 'TEST',
]);
$getrixListings = iterator_to_array($getrix->fetchListings($getrixFeed));
$assert(count($getrixListings) === 1, 'legge un nodo Immobile dal file archiviato');
$assert(($getrixListings[0]->externalId ?? '') === 'ABC-123', 'legge IDImmobile come attributo XML');
$assert(($getrixListings[0]->fields['tipologia_id'] ?? '') === '10', 'legge IDTipologia come attributo XML');
$assert(is_file($getrix->lastArtifactPath()), 'conserva lo ZIP originale della sync');
$assert(is_file(dirname($getrix->lastArtifactPath()).'/feed.xml'), 'conserva XML estratto');
$assert(is_file(dirname($getrix->lastArtifactPath()).'/metadata.json'), 'conserva metadati e hash');

$removeTestTree = static function (string $directory) use (&$removeTestTree): void {
    if (!is_dir($directory)) {
        return;
    }

    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
        $path = $directory.'/'.$entry;
        if (is_dir($path)) {
            $removeTestTree($path);
        } else {
            unlink($path);
        }
    }

    rmdir($directory);
};
$removeTestTree($testRoot);

echo "ImmobileQuery::order\n";
$Q = new \Wonder\Plugin\Immobili\Services\ImmobileQuery();
$assert($Q->order('recenti') === ['evidence DESC, id', 'DESC'], "recenti => id DESC");
$assert($Q->order('prezzo_asc') === ['evidence DESC, prezzo', 'ASC'], "prezzo_asc");
$assert($Q->order('prezzo_desc') === ['evidence DESC, prezzo', 'DESC'], "prezzo_desc");
$assert($Q->order('superficie_desc') === ['evidence DESC, superficie', 'DESC'], "superficie_desc");
$assert($Q->order('boh') === ['evidence DESC, id', 'DESC'], "default => recenti");

echo "ImmobileQuery::where\n";
$w = $Q->where([], false);
$assert(str_contains($w, "`visible` = 'true'"), "base: visible true");
$assert(str_contains($w, "`deleted` = 'false'"), "base: deleted false");
$assert(str_contains($w, "`sold` = 'false'"), "base: sold false (lista)");
$assert(str_contains($Q->where([], true), "`sold` = 'true'"), "base: sold true (venduti)");

$w = $Q->where(['q' => 'Roma'], false);
$assert(str_contains($w, "(LOWER(`nome`) LIKE '%roma%' OR"), "q => gruppo OR multi-colonna");
$assert(str_contains($w, "LOWER(`comune_nome`) LIKE '%roma%'"), "q => include comune_nome");
$assert(str_contains($w, "LOWER(`indirizzo`) LIKE '%roma%')"), "q => include indirizzo (chiude gruppo)");

$w = $Q->where(['q' => '50%'], false);
$assert(str_contains($w, "LOWER(`nome`) LIKE '%50\\\\%%'"), "q: wildcard % escaped");

$w = $Q->where(['comune' => 'Bergamo'], false);
$assert(str_contains($w, "LOWER(`comune_nome`) LIKE '%bergamo%'"), "comune => LIKE");

$w = $Q->where(['tipologia' => 'Villa'], false);
$assert(str_contains($w, "LOWER(`tipologia_nome`) LIKE '%villa%'"), "tipologia => LIKE");

$assert(str_contains($Q->where(['contratto' => 'A'], false), "UPPER(`contratto_id`) = 'A'"), "contratto A");
$assert(str_contains($Q->where(['contratto' => 'V'], false), "UPPER(`contratto_id`) <> 'A'"), "contratto V = non-A");

$w = $Q->where(['prezzo_min' => 100000], false);
$assert(str_contains($w, "UPPER(`trattativa_riservata`) = 'TRUE' OR `prezzo` = 0 OR `prezzo` >= 100000"), "prezzo_min con guardie");
$w = $Q->where(['prezzo_max' => 300000], false);
$assert(str_contains($w, "`prezzo` <= 300000"), "prezzo_max");

$w = $Q->where(['superficie_min' => 80], false);
$assert(str_contains($w, "`superficie` = 0 OR `superficie` >= 80"), "superficie_min con guardia zero");
$w = $Q->where(['superficie_max' => 200], false);
$assert(str_contains($w, "`superficie` <= 200"), "superficie_max");

$assert(str_contains($Q->where(['camere' => 3], false), "`n_camere` >= 3"), "camere");
$assert(str_contains($Q->where(['bagni' => 2], false), "`n_bagni` >= 2"), "bagni");

$assert(str_contains($Q->where(['comune' => "O'Brien"], false), "LIKE '%o\\'brien%'"), "apice escaped");

echo "ImmobilePresenter::searchFields\n";
$P = new \Wonder\Plugin\Immobili\Services\ImmobilePresenter();
$row = [
    'provider'      => 'gestim',
    'tipologia_id'  => '',
    'comune_id'     => '',
    'attributi'     => ['tipologia' => 'Villa', 'comune' => 'Milano'],
    'nome'          => 'Bella villa',
    'pub_indirizzo' => 'true',
    'strada'        => 'Via Roma',
    'indirizzo'     => '',
    'pub_civico'    => 'true',
    'civico'        => '10',
];
$sf = $P->searchFields($row);
$assert(($sf['comune_nome'] ?? '') === 'Milano', "comune_nome da attributi (fallback Gestim)");
$assert(($sf['tipologia_nome'] ?? '') === 'Villa', "tipologia_nome da attributi");
$assert(!array_key_exists('ricerca', $sf), "searchFields non produce più 'ricerca'");

echo "\n";
echo $failures === 0
    ? "OK — {$total} asserzioni passate\n"
    : "FAIL — {$failures}/{$total} asserzioni fallite\n";

exit($failures === 0 ? 0 : 1);
