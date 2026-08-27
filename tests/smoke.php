<?php

declare(strict_types=1);

/**
 * Smoke test del modulo Immobili.
 *
 * Verifica in isolamento gli helper puri (senza runtime del framework).
 * Esecuzione:  php tests/smoke.php
 */

// Autoloader di Composer: carica gli helper del modulo (files autoload), le
// classi del modulo (`Wonder\Plugin\Immobili\`) e quelle del framework riusate
// dagli helper puri (es. `Wonder\Support\Text\Slug`, usata da Slug::base).
// Non avvia il runtime del framework: nessun bootstrap, DB o config.
require __DIR__.'/../vendor/autoload.php';

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

echo "FormText::resolve senza __t() (fallback difensivo)\n";
$formText = \Wonder\Plugin\Immobili\Support\Forms\FormText::class;

// Senza __t() registrato il fallback è la chiave stessa: è il comportamento
// difensivo che serve quando le lang del modulo non sono ancora caricate.
// Va verificato qui, PRIMA di registrare lo stub __t() sotto: una volta
// dichiarata, una funzione globale non può essere "ritirata" nello stesso
// processo PHP, quindi da qui in poi __t() risulta sempre definita.
$assert(
    $formText::resolve('immobili', 'fields.nome') === 'forms.immobili.fields.nome',
    "resolve compone forms.<section>.<key>"
);
$assert($formText::resolve('residenze', 'x', 'ripiego') === 'ripiego', "il fallback esplicito viene restituito tale e quale");

// Da qui in avanti i dizionari del presenter (ImmobileForm::options(), via
// FormText::resolve) passano da __t(): senza bootstrap del framework la
// funzione non esiste, quindi la stubbiamo leggendo le traduzioni reali da
// lang/it/*.json. Come il framework reale (vedi PdfFacts::label), lancia se
// la chiave manca: i chiamanti difensivi la intercettano e ricadono sul
// fallback interno; qui invece verifichiamo l'italiano tradotto per davvero.
if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        static $cache = [];

        $segments = explode('.', $key);
        $namespace = array_shift($segments);

        if (!array_key_exists($namespace, $cache)) {
            $path = dirname(__DIR__)."/lang/it/{$namespace}.json";
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $cache[$namespace] = is_array($decoded) ? $decoded : [];
        }

        $value = $cache[$namespace];

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new RuntimeException("Traduzione mancante: {$key}");
            }
            $value = $value[$segment];
        }

        if (!is_string($value)) {
            throw new RuntimeException("Traduzione mancante: {$key}");
        }

        return $value;
    }
}

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

echo "Slug::base (slugify)\n";
$assert(\Wonder\Plugin\Immobili\Support\Slug::base(['Villa a Città Alta']) === 'villa-a-citta-alta', "translittera e slugghifica");
$assert(\Wonder\Plugin\Immobili\Support\Slug::base(['  Trilocale, Via Roma 10  ']) === 'trilocale-via-roma-10', "trim + separatori");
$assert(\Wonder\Plugin\Immobili\Support\Slug::base(['']) === 'immobile', "vuoto => fallback 'immobile'");

$assert(\Wonder\Plugin\Immobili\Support\Slug::base([''], 'residenza') === 'residenza', "vuoto + fallback esplicito => 'residenza'");
$assert(\Wonder\Plugin\Immobili\Support\Slug::base(['Corte Verde'], 'residenza') === 'corte-verde', "il fallback non interferisce quando c'è testo");

echo "Slug parametrico sul modello\n";
$slugReflection = new ReflectionMethod(\Wonder\Plugin\Immobili\Support\Slug::class, 'unique');
$slugParams = $slugReflection->getParameters();
$assert(count($slugParams) === 4, "Slug::unique accetta base, modelClass, excludeId, fallback");
$assert(($slugParams[1]->getName() ?? '') === 'modelClass', "il secondo parametro è il modello");
$assert(
    $slugParams[1]->isDefaultValueAvailable()
    && $slugParams[1]->getDefaultValue() === \Wonder\Plugin\Immobili\Models\Immobile::class,
    "Immobile resta il default"
);
$assert(
    !method_exists(\Wonder\Plugin\Immobili\Support\Forms\ResidenzaForm::class, 'uniqueSlug'),
    "ResidenzaForm::uniqueSlug è stata rimossa a favore di Slug"
);

echo "Slug dai campi del titolo\n";
$slugRow = [
    'tipologia_nome' => 'Trilocale',
    'strada'         => 'via roma',
    'indirizzo'      => '10',
    'comune_nome'    => 'Milano',
];
$slugBase = \Wonder\Plugin\Immobili\Support\Slug::base([
    \Wonder\Plugin\Immobili\Catalog\ImmobilePresenter::titolo($slugRow),
]);
$assert($slugBase === 'trilocale-via-roma-10-milano', "slug deriva da tipologia+strada+indirizzo+comune");

echo "immobiliFormatPrice\n";
$assert(immobiliFormatPrice(250000) === '€ 250.000', "prezzo formattato");
$assert(immobiliFormatPrice(0) === '', "0 => vuoto");
$assert(immobiliFormatPrice('1500') === '€ 1.500', "stringa numerica");

echo "ImmobilePresenter::formatSurface\n";
$assert(\Wonder\Plugin\Immobili\Catalog\ImmobilePresenter::formatSurface(120) === '120 mq', "superficie formattata");
$assert(\Wonder\Plugin\Immobili\Catalog\ImmobilePresenter::formatSurface(0) === '', "0 => vuoto");

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
// tipologia_id ora è risolto all'id canonico durante normalize (via getrix_id);
// senza DB delle tassonomie la risoluzione resta 0. Verifichiamo invece campi
// XML indipendenti dalle tassonomie.
$assert(($getrixListings[0]->fields['prezzo'] ?? '') === '250000', 'legge Prezzo dal nodo XML');
$assert(($getrixListings[0]->fields['contratto_id'] ?? '') === 'V', 'legge Contratto dal nodo XML');
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
$Q = new \Wonder\Plugin\Immobili\Catalog\ImmobileQuery();
$assert($Q->order('recenti') === ['evidence DESC, id', 'DESC'], "recenti => id DESC");
$assert($Q->order('prezzo_asc') === ['evidence DESC, prezzo', 'ASC'], "prezzo_asc");
$assert($Q->order('prezzo_desc') === ['evidence DESC, prezzo', 'DESC'], "prezzo_desc");
$assert($Q->order('superficie_asc') === ['evidence DESC, superficie', 'ASC'], "superficie_asc");
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
$P = new \Wonder\Plugin\Immobili\Catalog\ImmobilePresenter();
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

echo "ImmobilePresenter::detailFields\n";
$details = $P->detailFields([
    'provider'       => 'getrix',
    'n_camere'       => '2',
    'n_balconi'      => '1',
    'n_terrazzi'     => '0',
    'n_posti_auto'   => '1',
    'piani_edificio' => '5',
    'attributi'      => [
        'NrAltreCamere'       => '1',
        'Cucina'              => '1',
        'GiardinoPrivato'     => '2',
        'BoxAuto'             => '1',
        'Cantina'             => '1',
        'Mansarda'            => '2',
        'Arredamento'         => '255',
        'InfissiEsterni'      => '5',
        'ImpiantoTV'          => '1',
        'PortaBlindata'       => 'true',
        'Allarme'             => 'false',
        'VideoCitofono'       => 'true',
        'Caminetto'           => 'false',
        'Tennis'              => 'false',
        'TipoCostruzione'     => '4',
        'StatoManutenzione'   => '6',
        'NrAscensori'         => '1',
    ],
]);
$assert(($details['altre_camere'] ?? null) === 1, 'legge la chiave Getrix NrAltreCamere');
$assert(($details['totale_camere'] ?? null) === 3, 'calcola il totale camere dal presenter');
$assert(($details['cucina'] ?? '') === 'Abitabile', 'traduce il codice Getrix Cucina');
$assert(($details['giardino'] ?? '') === 'No', 'traduce il codice Getrix GiardinoPrivato');
$assert(($details['box_auto'] ?? '') === 'Singolo', 'traduce il codice Getrix BoxAuto');
$assert(($details['balcone'] ?? '') === 'Sì', 'presenta NrBalconi dalla colonna canonica');
$assert(($details['terrazzo'] ?? '') === 'No', 'presenta NrTerrazzi dalla colonna canonica');
$assert(($details['infissi_esterni'] ?? '') === 'Doppio vetro/legno', 'traduce InfissiEsterni');
$assert(($details['videocitofono'] ?? '') === 'Sì', 'legge la chiave Getrix VideoCitofono');
$assert(($details['camino'] ?? '') === 'No', 'legge la chiave Getrix Caminetto');
$assert(($details['campo_tennis'] ?? '') === 'No', 'legge la chiave Getrix Tennis');
$assert(($details['classe_immobile'] ?? '') === 'Signorile', 'deriva la classe da TipoCostruzione');
$assert(($details['stato_immobile'] ?? '') === 'Ottimo', 'deriva lo stato da StatoManutenzione');
$assert(($details['ascensore'] ?? '') === 'Sì', 'legge la chiave Getrix NrAscensori');

echo "ImmobilePresenter::detailFields (manuale)\n";
$manualDetails = $P->detailFields([
    'provider'          => 'manual',
    'n_camere'          => '2',
    'n_altre_camere'    => '1',
    'n_posti_auto'      => '1',
    'n_balconi'         => 'true',
    'n_terrazzi'        => 'false',
    'n_ascensori'       => 'true',
    'piani_edificio'    => '5',
    'cucina_id'         => '1',
    'box_auto_id'       => '1',
    'arredamento_id'    => '255',
    'infissi_esterni_id' => '5',
    'impianto_tv_id'    => '1',
    'cantina_id'        => '1',
    'mansarda_id'       => '2',
    'giardino_privato_id' => '2',
    'giardino_condominiale' => 'true',
    'porta_blindata'    => 'true',
    'videocitofono'     => 'true',
    'camino'            => 'false',
    'tennis'            => 'false',
    'tipo_costruzione_id' => '4',
    'stato_costruzione_id' => '6',
]);
$assert(($manualDetails['altre_camere'] ?? null) === 1, 'manuale: altre camere dalla colonna n_altre_camere');
$assert(($manualDetails['totale_camere'] ?? null) === 3, 'manuale: totale camere calcolato');
$assert(($manualDetails['cucina'] ?? '') === 'Abitabile', 'manuale: traduce cucina_id');
$assert(($manualDetails['box_auto'] ?? '') === 'Singolo', 'manuale: traduce box_auto_id');
$assert(($manualDetails['arredamento'] ?? '') === 'Assente', 'manuale: traduce arredamento_id');
$assert(($manualDetails['infissi_esterni'] ?? '') === 'Doppio vetro/legno', 'manuale: traduce infissi_esterni_id');
$assert(($manualDetails['cantina'] ?? '') === 'Sì', 'manuale: presenza cantina_id');
$assert(($manualDetails['giardino'] ?? '') === 'Condominiale', 'manuale: giardino da privato+condominiale');
$assert(($manualDetails['balcone'] ?? '') === 'Sì', 'manuale: balcone booleano dalla colonna');
$assert(($manualDetails['terrazzo'] ?? '') === 'No', 'manuale: terrazzo booleano dalla colonna');
$assert(($manualDetails['ascensore'] ?? '') === 'Sì', 'manuale: ascensore booleano dalla colonna');
$assert(($manualDetails['classe_immobile'] ?? '') === 'Signorile', 'manuale: classe da tipo_costruzione_id');
$assert(($manualDetails['stato_immobile'] ?? '') === 'Ottimo', 'manuale: stato da stato_costruzione_id');

echo "PDF con font personalizzati\n";
$GLOBALS['ROOT_APP'] = dirname(__DIR__).'/vendor/wonder-image/app/app';
$pdfContext = new \Wonder\Plugin\Immobili\Pdf\PdfContext(
    new \Wonder\Plugin\Immobili\Pdf\Support\Color(31, 111, 235),
    new \Wonder\Plugin\Immobili\Pdf\Support\Color(11, 61, 145),
    'Montserrat-Regular',
    'Montserrat-Bold',
    '',
    new \Wonder\Plugin\Immobili\Pdf\Contacts(
        'Agenzia Test',
        '+39 02 123456',
        'info@example.test',
        'example.test',
        'Via Roma 1, Milano'
    ),
);
$pdfImmobile = (object) [
    'id'            => 1,
    'slug'          => 'test',
    'titolo'        => 'Appartamento | Via Roma 10, Milano',
    'prettyName'    => 'Appartamento in centro',
    'prettyAddress' => 'Via Roma 10, Milano',
    'contratto'     => 'Vendita',
    'prezzo'        => '€ 250.000',
    'descrizione'   => 'Appartamento luminoso.',
    'images'        => [],
];
$pdfRow = [
    'id'           => 1,
    'slug'         => 'test',
    'nome'         => 'RIF-001',
    'contratto_id' => 'V',
    'prezzo'       => 250000,
];

ob_start();
$pdfBytes = (new \Wonder\Plugin\Immobili\Pdf\Document\SchedaImmobile(
    $pdfImmobile,
    $pdfRow,
    $pdfContext,
    \Wonder\Plugin\Immobili\Pdf\PdfConfig::defaults()['scheda'],
))->build();
$pdfNoise = ob_get_clean();

$assert($pdfNoise === '', 'il caricamento dei font non stampa avvisi prima del PDF');
$assert(str_starts_with($pdfBytes, '%PDF-'), 'lo stream inizia direttamente con la firma PDF');
$assert(
    \Wonder\Plugin\Immobili\Pdf\Support\PdfText::plain('All&#8217;esterno') === 'All’esterno',
    'le entità HTML editoriali diventano testo leggibile'
);

$GLOBALS['ROOT'] = dirname(__DIR__);
$resolvedPdfFile = \Wonder\Plugin\Immobili\Pdf\Support\ImageFitter::resolve('https://example.test/composer.json');
$assert(
    realpath($resolvedPdfFile) === realpath(dirname(__DIR__).'/composer.json'),
    'gli URL locali sono risolti sul filesystem'
);

echo "FormText: classi energetiche condivise\n";
// `resolve()` è già stato verificato in cima al file, prima che lo stub __t()
// venisse registrato: lì si controlla il fallback difensivo, qui il catalogo.
$energy = $formText::energyClasses();
$assert(($energy[''] ?? null) === '--', "la prima opzione è il placeholder vuoto");
$assert(array_key_exists('A4', $energy) && array_key_exists('G', $energy), "copre le classi di entrambe le leggi");
$assert(
    \Wonder\Plugin\Immobili\Support\Forms\ImmobileForm::energyClasses() === $energy,
    "ImmobileForm::energyClasses delega alla base condivisa"
);
$assert(
    \Wonder\Plugin\Immobili\Support\Forms\ResidenzaForm::energyClasses() === $energy,
    "ResidenzaForm::energyClasses delega alla base condivisa"
);

echo "Dizionari del presenter allineati alle traduzioni\n";
$presenterReflection = new ReflectionClass(\Wonder\Plugin\Immobili\Catalog\ImmobilePresenter::class);
$hardcoded = array_intersect(
    array_keys($presenterReflection->getConstants()),
    ['KITCHEN', 'GARAGE', 'FURNISHING', 'WINDOW_FRAMES', 'TV_SYSTEM', 'CONSTRUCTION_TYPE', 'MAINTENANCE_STATE']
);
$assert($hardcoded === [], 'nessun dizionario di dominio resta hardcoded nel presenter: '.implode(', ', $hardcoded));

$constructionKeys = (new ReflectionClass(\Wonder\Plugin\Immobili\Support\Forms\ImmobileForm::class))
    ->getConstant('OPTION_KEYS')['construction_type'] ?? [];
$assert(($constructionKeys['255'] ?? '') === 'other', "il codice 255 di construction_type è 'other', non 'standard'");

foreach (['it', 'en'] as $locale) {
    $forms = json_decode((string) file_get_contents(dirname(__DIR__)."/lang/{$locale}/forms.json"), true);
    $assert(
        isset($forms['immobili']['options']['construction_type']['other']),
        "lang/{$locale}: construction_type.other è tradotta"
    );
}

echo "MediaUrl\n";
$GLOBALS['PATH'] = (object) ['upload' => 'https://example.test/upload', 'rUpload' => '/srv/upload'];
$mediaUrl = \Wonder\Plugin\Immobili\Media\MediaUrl::class;

$assert($mediaUrl::url('', 'residenze') === '', "file vuoto => ''");
$assert($mediaUrl::url('a.jpg', 'residenze') === 'https://example.test/upload/residenze/a.jpg', "URL composto su cartella");
$assert(
    $mediaUrl::url('https://cdn.test/x.jpg', 'residenze') === 'https://cdn.test/x.jpg',
    "URL assoluto passa invariato"
);
$assert($mediaUrl::preview('a.jpg', 'residenze') === 'https://example.test/upload/residenze/a-620.webp', "anteprima => variante -620.webp");
$assert($mediaUrl::preview('a.b.jpg', 'residenze') === 'https://example.test/upload/residenze/a.b-620.webp', "estensione tagliata sull'ultimo punto");
$assert(
    $mediaUrl::preview('https://cdn.test/x.jpg', 'residenze') === 'https://cdn.test/x.jpg',
    "gli URL assoluti non hanno varianti responsive"
);
$assert($mediaUrl::variant('a.jpg', 'immobili', 1200) === 'https://example.test/upload/immobili/a-1200.webp', "variante a larghezza esplicita");

$assert($mediaUrl::firstFile('["uno.jpg","due.jpg"]') === 'uno.jpg', "firstFile da JSON");
$assert($mediaUrl::firstFile(['uno.jpg', 'due.jpg']) === 'uno.jpg', "firstFile da array già decodificato");
$assert($mediaUrl::firstFile('uno.jpg') === 'uno.jpg', "firstFile da stringa legacy");
$assert($mediaUrl::firstFile('') === '', "firstFile su vuoto => ''");
$assert($mediaUrl::firstFile('[broken') === '', "JSON malformato => '' (non finisce mai in un URL)");
$assert($mediaUrl::firstFile('{"a":1') === '', "oggetto JSON troncato => ''");
$assert($mediaUrl::firstFile('"uno.jpg"') === 'uno.jpg', "stringa JSON valida => filename");

// Estrae gli IDENTIFICATORI del codice PHP di una view: via commenti, HTML
// inline e stringhe letterali — li' il nome del modulo compare per forza, nei
// path degli asset e nelle chiavi di traduzione, senza essere una
// ramificazione. Restano nomi di classe, variabili e strutture di controllo:
// e' li' che una ramificazione per reparto (`$residenza`, `ResidenzaPresenter`,
// `instanceof Residenza`) si vedrebbe.
$executableCode = static function (string $file): string {
    $code = implode(' ', array_map(
        static fn (array $t): string => is_array($t) ? (string) $t[1] : '',
        array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            static fn ($t): bool => is_array($t)
                && !in_array($t[0], [
                    T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML,
                    T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE,
                ], true)
        ))
    ));

    // Via il nome della classe entrypoint e del namespace (parola esatta
    // `Immobili`): è il modulo, non un reparto. Restano `$immobile`,
    // `Residenza`, `ResidenzaPresenter` e simili, che invece lo sono.
    return (string) preg_replace('/\\\\?\\bImmobili\\b/', '', $code);
};

echo "CardViewModel — forma comune ai due reparti\n";
$vmClass = \Wonder\Plugin\Immobili\Catalog\CardViewModel::class;

$immobileVm = $vmClass::fromImmobile((object) [
    'url'              => '/immobili/trilocale-milano/',
    'cover'            => 'https://example.test/upload/immobili/a-620.webp',
    'sold'             => false,
    'evidence'         => true,
    'tipologia'        => 'Trilocale',
    'contratto'        => 'Vendita',
    'prettyName'       => 'Trilocale, Via Roma 10',
    'prettyAddress'    => 'Via Roma 10 — Milano',
    'prezzo'           => 250000,
    'prettyPrezzo'     => '€ 250.000',
    'prettySuperficie' => '120 mq',
    'locali'           => 3,
    'camere'           => 2,
    'bagni'            => 1,
]);

$residenzaVm = $vmClass::fromResidenza([
    'url'               => '/residenze/corte-verde/',
    'nome'              => 'Corte Verde',
    'comune_nome'       => 'Bergamo',
    'descrizione_breve' => 'Otto unità in classe A.',
    'images'            => '[]',
    'sold'              => 'false',
    'stato'             => 'in_corso',
    'inizio_anno'       => 2025,
    'inizio_mese'       => 3,
    'fine_anno'         => 2026,
    'fine_mese'         => 0,
]);

$vmKeys = static fn (object $vm): array => array_keys(get_object_vars($vm));
$assert($vmKeys($immobileVm) === $vmKeys($residenzaVm), 'i due reparti espongono esattamente le stesse chiavi');

foreach (['url', 'cover', 'eyebrow', 'title', 'subtitle', 'highlight', 'excerpt'] as $stringField) {
    $assert(is_string($immobileVm->$stringField), "immobile.{$stringField} è sempre string");
    $assert(is_string($residenzaVm->$stringField), "residenza.{$stringField} è sempre string");
}

$assert($immobileVm->title === 'Trilocale, Via Roma 10', 'immobile: title = prettyName');
$assert($immobileVm->highlight === '€ 250.000', 'immobile: highlight = prezzo formattato');
$assert($immobileVm->excerpt === '', 'immobile: excerpt vuoto, non assente');
$assert($immobileVm->eyebrow === 'Trilocale · Vendita', 'immobile: eyebrow = tipologia · contratto');

$assert($residenzaVm->title === 'Corte Verde', 'residenza: title = nome');
$assert($residenzaVm->subtitle === 'Bergamo', 'residenza: subtitle = comune');
$assert($residenzaVm->highlight === '', 'residenza: highlight vuoto, non assente');
$assert($residenzaVm->excerpt === 'Otto unità in classe A.', 'residenza: excerpt = descrizione breve');

$assert(is_object($immobileVm->badge) && $immobileVm->badge->label !== '', 'immobile in evidenza ha un badge');
$assert(is_object($residenzaVm->badge), 'la residenza ha sempre il badge di stato');

$assert(is_array($immobileVm->meta) && count($immobileVm->meta) === 4, 'immobile: 4 voci meta (mq, locali, camere, bagni)');
$assert(
    ($immobileVm->meta[0]->text ?? '') === '120 mq',
    'immobile: la superficie è formattata, non il valore grezzo'
);
$assert(is_array($residenzaVm->meta), 'residenza: meta è sempre array');
$assert(
    ($residenzaVm->meta[0]->text ?? '') === '03/2025 → 2026',
    'residenza: la timeline compare tra i meta'
);

// Il criterio è che le card non RAMIFICHINO per reparto: i docblock possono
// nominarli per spiegare il concetto, il codice eseguibile no.
$cardCode = $executableCode(dirname(__DIR__).'/view/components/card.php');
$assert(
    stripos($cardCode, 'residenz') === false && stripos($cardCode, 'immobil') === false,
    'card.php non conosce i reparti: nessun ramo per tipo nel codice eseguibile'
);

echo "Varianti di card\n";
$cardDir = dirname(__DIR__).'/view/components/card';

$assert(
    \Wonder\Plugin\Immobili\Catalog\CardViewModel::VARIANTS === ['base', 'overlay', 'overlay-rich'],
    'le varianti dichiarate sono base, overlay, overlay-rich'
);

foreach (\Wonder\Plugin\Immobili\Catalog\CardViewModel::VARIANTS as $variant) {
    $assert(is_file($cardDir.'/'.$variant.'.php'), "esiste il file della variante {$variant}");
}
$assert(is_file($cardDir.'/media.php'), 'esiste il componente media condiviso');

// Il dispatcher deve ripiegare su base: in lista una variante sconosciuta che
// non produce nulla lascerebbe un buco silenzioso, difficile da diagnosticare.
$dispatcher = (string) file_get_contents(dirname(__DIR__).'/view/components/card.php');
$assert(str_contains($dispatcher, 'VARIANTS'), 'il dispatcher valida contro le varianti dichiarate');
$assert(str_contains($dispatcher, "\$variant = 'base'"), 'una variante sconosciuta ricade su base');

// Nessuna variante deve conoscere i reparti: e' il patto del view-model.
foreach (['base', 'overlay', 'overlay-rich', 'media'] as $file) {
    $code = $executableCode($cardDir.'/'.$file.'.php');
    $assert(
        stripos($code, 'residenz') === false && stripos($code, 'immobil') === false,
        "card/{$file}.php non ramifica per reparto"
    );
}

// La gallery non deve trascinarsi dietro Swiper: in una griglia servirebbe
// un'istanza per card.
$media = (string) file_get_contents($cardDir.'/media.php');
$assert(!str_contains($media, '__swiper'), 'la gallery in-card non usa Swiper');
$assert(str_contains($media, 'immobili-card.js'), 'la gallery carica il proprio JS');

$assert(
    method_exists(\Wonder\Plugin\Immobili\Immobili::class, 'scriptOnce'),
    'Immobili::scriptOnce esiste, speculare a styleOnce'
);

// images: popolate per le residenze (colonna JSON gia' letta), e mai inventate
// per gli immobili, dove costerebbero una query per riga.
$vmImmobile = \Wonder\Plugin\Immobili\Catalog\CardViewModel::fromImmobile((object) [
    'url' => '/x/', 'cover' => 'https://e.test/a-620.webp',
]);
$assert($vmImmobile->images === ['https://e.test/a-620.webp'], 'senza foto esplicite images e la sola cover');

$vmConImmagini = \Wonder\Plugin\Immobili\Catalog\CardViewModel::fromImmobile((object) [
    'url' => '/x/', 'cover' => 'https://e.test/a-620.webp',
    'images' => ['https://e.test/a-620.webp', 'https://e.test/b-620.webp'],
]);
$assert(count($vmConImmagini->images) === 2, 'le foto fornite a monte finiscono nel view-model');

foreach (['it', 'en'] as $locale) {
    $components = json_decode((string) file_get_contents(dirname(__DIR__)."/lang/{$locale}/components.json"), true);
    foreach (['gallery_prev', 'gallery_next'] as $key) {
        $assert(
            isset($components['immobili']['card'][$key]),
            "lang/{$locale}: components.immobili.card.{$key} tradotta"
        );
    }
}

echo "Componente cards unificato\n";
$viewDir = dirname(__DIR__).'/view/components';
$assert(is_file($viewDir.'/cards.php'), 'cards.php esiste');
$assert(!is_file($viewDir.'/cards-grid.php'), 'cards-grid.php è stato assorbito');
$assert(!is_file($viewDir.'/cards-swiper.php'), 'cards-swiper.php è stato assorbito');
$assert(!is_file($viewDir.'/residenze/card.php'), 'la card delle residenze è stata unificata');

$cardsSource = (string) file_get_contents($viewDir.'/cards.php');
$assert(str_contains($cardsSource, "'swiper'"), 'cards.php gestisce il layout swiper');
$assert(str_contains($cardsSource, 'd-grid'), 'cards.php gestisce il layout griglia');

$residenzeList = (string) file_get_contents(dirname(__DIR__).'/view/pages/frontend/residenze/list.php');
$assert(
    !str_contains($residenzeList, 'd-grid col-3'),
    'la lista residenze non ha più la griglia scritta a mano'
);

echo "Ogni classe del modulo è raggiungibile dal punto in cui viene usata\n";
// Questo test esiste per una classe di bug che la riorganizzazione dei
// namespace introduce in silenzio: due classi prima co-locate finiscono in
// namespace diversi, e il riferimento NON qualificato che prima funzionava
// (`new ImmobilePresenter()`) continua a compilare ma esplode a runtime, perché
// PHP lo cerca nel namespace corrente. Nessuna analisi di sintassi lo vede: si
// manifesta solo eseguendo il codice — nel nostro caso durante un sync reale.
$moduleRoot = dirname(__DIR__).'/src';
$classHome = [];

$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot));

foreach ($phpFiles as $file) {
    if ($file->isDir() || !str_ends_with($file->getPathname(), '.php')) {
        continue;
    }

    $source = (string) file_get_contents($file->getPathname());

    if (preg_match('/^namespace\s+([^;]+);/m', $source, $ns)
        && preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $source, $cls)) {
        $classHome[$cls[1]] = ltrim(str_replace('Wonder\\Plugin\\Immobili', '', trim($ns[1])), '\\');
    }
}

$unreachable = [];

foreach ($phpFiles as $file) {
    if ($file->isDir() || !str_ends_with($file->getPathname(), '.php')) {
        continue;
    }

    $source = (string) file_get_contents($file->getPathname());
    preg_match('/^namespace\s+([^;]+);/m', $source, $ns);
    $here = ltrim(str_replace('Wonder\\Plugin\\Immobili', '', trim($ns[1] ?? '')), '\\');
    preg_match_all('/^use\s+[\w\\\\]*\\\\(\w+);/m', $source, $u);
    $imported = $u[1] ?? [];

    // Solo identificatori di codice: commenti e stringhe nominano le classi
    // senza usarle davvero. Si scartano anche i nomi preceduti da `->` o `::`,
    // che sono metodi e proprietà: `$import->Immobili()` non è la classe.
    $used = [];
    $tokens = array_values(token_get_all($source));

    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $prev = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                continue;
            }
            $prev = $tokens[$j];
            break;
        }

        if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
            continue;
        }

        $used[] = $token[1];
    }

    foreach ($classHome as $class => $home) {
        if ($home === $here || in_array($class, $imported, true) || !in_array($class, $used, true)) {
            continue;
        }

        $unreachable[] = basename($file->getPathname()).": {$class} (vive in ".($home ?: 'root').")";
    }
}

$assert(
    $unreachable === [],
    'nessun uso non qualificato di una classe che vive in un altro namespace: '.implode('; ', $unreachable)
);

echo "GetrixProvider: guardia e adozione delle tassonomie\n";
$getrix = \Wonder\Plugin\Immobili\Feed\GetrixProvider::class;
$getrixRef = new ReflectionClass($getrix);

$assert(
    \Wonder\Plugin\Immobili\Support\Taxonomy::providerColumn('getrix') === 'getrix_id',
    'la colonna mappa del provider getrix è getrix_id'
);

// upsert() adotta le righe legacy invece di duplicarle: senza il quarto
// parametro, ogni passata su una tabella popolata da una versione precedente
// (nome presente, chiave naturale assente) creerebbe un duplicato.
$upsertParams = $getrixRef->getMethod('upsert')->getParameters();
$assert(count($upsertParams) === 4, 'upsert accetta anche il criterio di adozione');
$assert(($upsertParams[3]->getName() ?? '') === 'adopt', 'il quarto parametro si chiama adopt');
$assert(
    $upsertParams[3]->isDefaultValueAvailable() && $upsertParams[3]->getDefaultValue() === [],
    'adopt è opzionale: i chiamanti che non ne hanno bisogno restano invariati'
);

// La guardia deve misurare l'USABILITÀ del set, non la sua presenza: una
// tabella popolata senza getrix_id supera un controllo di sola presenza e
// blocca il seeding per sempre. Il test guarda il sorgente perché il metodo è
// privato e interroga il database, che smoke.php non ha.
$guardSource = (string) file_get_contents($getrixRef->getFileName());
$guardBody = substr($guardSource, strpos($guardSource, 'function hasBaseTaxonomies'));
$guardBody = substr($guardBody, 0, strpos($guardBody, 'private function hasMappedRows'));
$assert(
    str_contains($guardBody, 'providerColumn') && str_contains($guardBody, 'hasMappedRows'),
    'hasBaseTaxonomies verifica la mappatura del provider, non la sola presenza di righe'
);
$assert(
    !preg_match('/find\(\[\],\s*1\)/', $guardBody),
    'hasBaseTaxonomies non si accontenta più di una riga qualsiasi'
);
$assert(
    $getrixRef->hasMethod('hasMappedRows'),
    'esiste il controllo dedicato sulle righe mappate'
);

// Ogni upsert che match-a su `chiave` DEVE passare un criterio di adozione:
// `chiave` è proprio il campo che le tabelle popolate da versioni precedenti
// non hanno, quindi senza adozione il match fallisce sempre e ogni passata
// aggiunge un duplicato. Le tassonomie geografiche che match-ano su `sigla` o
// `cod_catastale` non ne hanno bisogno: quei campi erano già valorizzati.
$getrixSource = (string) file_get_contents($getrixRef->getFileName());
preg_match_all(
    '/upsert\(\s*(\w+)::class,\s*\[\s*.chiave.\s*=>.*?\]\s*\)?;/s',
    $getrixSource,
    $chiaveUpserts,
    PREG_SET_ORDER
);
$senzaAdozione = [];

foreach ($chiaveUpserts as $match) {
    if (!str_contains($match[0], "], ['nome'")) {
        $senzaAdozione[] = $match[1];
    }
}

$assert(
    $senzaAdozione === [],
    'ogni upsert su `chiave` adotta le righe legacy: '.implode(', ', $senzaAdozione)
);
$assert(count($chiaveUpserts) === 4, 'sono quattro gli upsert che match-ano su chiave');

echo "searchFields non cancella i nomi denormalizzati\n";
$sfPresenter = new \Wonder\Plugin\Immobili\Catalog\ImmobilePresenter();

// FK orfane (le tabelle tassonomia sono state riseminate e gli id sono cambiati)
// e nessun attributo nativo di fallback: il valore già salvato deve sopravvivere,
// altrimenti un backfill manda in bianco titoli e indirizzi della lista.
$orfano = $sfPresenter->searchFields([
    'provider'       => 'getrix',
    'tipologia_id'   => 999999,
    'comune_id'      => 999999,
    'attributi'      => '{"LeggeClasseEnergetica":"1"}',
    'tipologia_nome' => 'Box',
    'comune_nome'    => 'Milano',
]);
$assert($orfano['tipologia_nome'] === 'Box', 'FK orfana: la tipologia già salvata sopravvive');
$assert($orfano['comune_nome'] === 'Milano', 'FK orfana: il comune già salvato sopravvive');

// Senza FK, senza attributi e senza valore pregresso resta vuoto: non si inventa nulla.
$vuoto = $sfPresenter->searchFields(['provider' => 'manual', 'tipologia_id' => 0, 'comune_id' => 0]);
$assert($vuoto['tipologia_nome'] === '', 'nessuna fonte disponibile => stringa vuota');
$assert($vuoto['comune_nome'] === '', 'nessuna fonte disponibile => comune vuoto');

// Gli attributi nativi del feed hanno la precedenza sul valore pregresso.
$daFeed = $sfPresenter->searchFields([
    'provider'       => 'gestim',
    'tipologia_id'   => 999999,
    'attributi'      => '{"tipologia":"Attico","comune":"Bergamo"}',
    'tipologia_nome' => 'Box',
    'comune_nome'    => 'Milano',
]);
$assert($daFeed['tipologia_nome'] === 'Attico', 'gli attributi del feed battono il valore pregresso');
$assert($daFeed['comune_nome'] === 'Bergamo', 'idem per il comune');

echo "ReindexService\n";
$reindex = \Wonder\Plugin\Immobili\Sync\ReindexService::class;
$assert(class_exists($reindex), 'ReindexService esiste');
$assert(method_exists($reindex, 'run'), 'espone run()');
$assert(
    (new ReflectionMethod($reindex, 'run'))->getNumberOfRequiredParameters() === 0,
    'run() non richiede parametri'
);

$reindexHandler = (string) file_get_contents(dirname(__DIR__).'/http/api/task/reindex.php');
$assert(
    !str_contains($reindexHandler, 'Immobile::update')
    && !str_contains($reindexHandler, 'Slug::unique'),
    "l'handler non contiene più logica di dominio"
);
$assert(str_contains($reindexHandler, 'ReindexService'), "l'handler delega al service");

echo "Slug localizzati dei due reparti\n";
foreach (['it', 'en'] as $locale) {
    $urls = json_decode((string) file_get_contents(dirname(__DIR__)."/lang/{$locale}/urls.json"), true);
    $assert(is_array($urls), "lang/{$locale}/urls.json è JSON valido");

    foreach (['immobili', 'immobili/list', 'immobili/sold', 'residenze', 'residenze/list'] as $key) {
        $assert(isset($urls[$key]) && $urls[$key] !== '', "lang/{$locale}: '{$key}' ha uno slug");
    }

    $assert(
        str_starts_with((string) ($urls['residenze'] ?? ''), $locale.'/'),
        "lang/{$locale}: lo slug residenze è prefissato dal locale"
    );
}

echo "Simmetria delle cartelle view\n";
$viewRoot = dirname(__DIR__).'/view';

foreach ([
    'components/card.php', 'components/cards.php', 'components/specs.php',
    'components/amenities.php', 'components/map.php',
    'components/energy-class/energy-class.php',
    'components/immobili/filters.php', 'components/residenze/timeline.php',
    'pages/frontend/immobili/list.php', 'pages/frontend/immobili/detail.php',
    'pages/frontend/immobili/sold.php',
    'pages/frontend/residenze/list.php', 'pages/frontend/residenze/detail.php',
] as $expected) {
    $assert(is_file($viewRoot.'/'.$expected), "esiste view/{$expected}");
}

foreach ([
    'components/features.php', 'components/filters.php',
    'components/residenze/features.php', 'components/residenze/card.php',
    'pages/frontend/list.php', 'pages/frontend/detail.php', 'pages/frontend/sold.php',
] as $gone) {
    $assert(!is_file($viewRoot.'/'.$gone), "view/{$gone} è stato spostato");
}

// Ogni handler dichiarato nelle route frontend deve esistere davvero: è il
// controllo che intercetta uno spostamento di view non riflesso nelle route.
$frontendRoutes = \Wonder\Http\Route::load([dirname(__DIR__).'/config/routes/route.frontend.php']);
$missingHandlers = [];

foreach ($frontendRoutes as $route) {
    $handler = (string) ($route['handler'] ?? '');
    if ($handler !== '' && !is_file($handler)) {
        $missingHandlers[] = ($route['name'] ?? '?').' → '.$handler;
    }
}

$assert($missingHandlers === [], 'tutte le route frontend puntano a file esistenti: '.implode('; ', $missingHandlers));

// La residenza usa il componente condiviso della classe energetica invece di
// stampare un badge a mano.
$residenzeDetail = (string) file_get_contents($viewRoot.'/pages/frontend/residenze/detail.php');
$assert(
    str_contains($residenzeDetail, "component('energy-class/badge'"),
    'la scheda residenza usa il componente energy-class condiviso'
);
$assert(
    !str_contains($residenzeDetail, 'text-bg-success'),
    'il badge energetico scritto a mano è sparito dalla scheda residenza'
);

echo "Gate unico dei task API\n";
$taskDir = dirname(__DIR__).'/http/api/task';
$assert(is_file($taskDir.'/_guard.php'), '_guard.php esiste');

// Il blocco auth era ripetuto verbatim nei tre handler: nessuno di loro deve
// più contenerne un pezzo, altrimenti la deduplicazione è solo apparente.
foreach (['seed', 'residenze-seed', 'reindex'] as $handler) {
    $source = (string) file_get_contents($taskDir.'/'.$handler.'.php');
    $assert(str_contains($source, "_guard.php"), "{$handler}.php richiede il guard");
    $assert(str_contains($source, 'immobiliTaskGuard('), "{$handler}.php invoca il guard");
    $assert(
        !str_contains($source, 'getallheaders')
        && !str_contains($source, 'REDIRECT_HTTP_AUTHORIZATION')
        && !str_contains($source, 'isLocal'),
        "{$handler}.php non ha più una copia del blocco auth"
    );
}

require_once $taskDir.'/_guard.php';

$hostWas = $_SERVER['HTTP_HOST'] ?? null;
foreach (['immobili.test', 'localhost', '127.0.0.1', 'sito.local', 'x.ddev.site'] as $localHost) {
    $_SERVER['HTTP_HOST'] = $localHost;
    $assert(immobiliTaskIsLocal(), "'{$localHost}' è riconosciuto come ambiente locale");
}
$_SERVER['HTTP_HOST'] = 'www.esempio.it';
$assert(!immobiliTaskIsLocal(), "un dominio pubblico non è ambiente locale");

$_GET['token'] = 'dal-query-string';
$assert(immobiliTaskPresentedToken() === 'dal-query-string', "il token arriva anche da ?token= (push Gestim)");
unset($_GET['token']);
$assert(immobiliTaskPresentedToken() === '', "senza header né query il token è vuoto");

if ($hostWas === null) { unset($_SERVER['HTTP_HOST']); } else { $_SERVER['HTTP_HOST'] = $hostWas; }

echo "Route cartello vetrina venduto\n";
$pdfRoutes = \Wonder\Http\Route::load([dirname(__DIR__).'/config/routes/route.frontend.php']);
$soldRoute = current(array_filter(
    $pdfRoutes,
    static fn (array $route): bool => ($route['name'] ?? '') === 'immobile.cartello.vetrina.venduto'
));
$assert(is_array($soldRoute) && is_file((string) ($soldRoute['handler'] ?? '')), 'la route usa un handler esistente');
$assert(($soldRoute['sold'] ?? false) === true, 'la route forza la variante venduto senza query nel path');

echo "\n";
echo $failures === 0
    ? "OK — {$total} asserzioni passate\n"
    : "FAIL — {$failures}/{$total} asserzioni fallite\n";

exit($failures === 0 ? 0 : 1);
