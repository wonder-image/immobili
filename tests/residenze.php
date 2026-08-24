<?php

declare(strict_types=1);

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Models\ResidenzaImmagine;

/**
 * Smoke test strutturale del reparto Residenze.
 * Esecuzione: php tests/residenze.php
 * Non apre connessioni al database: verifica schema e logica pura.
 */

defined('APP_URL') || define('APP_URL', 'https://example.test');
defined('ROOT') || define('ROOT', sys_get_temp_dir());
defined('ASSETS_VERSION') || define('ASSETS_VERSION', 'test');
defined('APP_VERSION') || define('APP_VERSION', '2.2.0');

require __DIR__.'/../vendor/autoload.php';

if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        return $key;
    }
}

$failures = 0;
$assertions = 0;
$assert = static function (bool $condition, string $message, string $details = '') use (&$failures, &$assertions): void {
    $assertions++;
    if ($condition) {
        echo "  \u{2713} {$message}\n";
        return;
    }
    $failures++;
    echo "  \u{2717} {$message}\n";
    if ($details !== '') {
        echo "    {$details}\n";
    }
};

echo "Schema Residenza\n";
$columns = Residenza::getColumns();
$columnNames = array_keys($columns);

$expectedColumns = [
    'code', 'nome', 'slug', 'logo', 'sito_url',
    'inizio_anno', 'inizio_mese', 'fine_anno', 'fine_mese',
    'descrizione_breve', 'descrizione_lunga',
    'indirizzo', 'civico', 'cap', 'comune_id', 'comune_nome',
    'latitudine', 'longitudine', 'zoom',
    'classe_energetica', 'unita_abitative', 'features', 'capitolato',
    'sold', 'stato', 'visible', 'evidence', 'position',
];
$assert(
    array_values(array_intersect($expectedColumns, $columnNames)) === $expectedColumns,
    'Residenza dichiara tutte le colonne di dominio previste',
    'mancanti: '.implode(', ', array_values(array_diff($expectedColumns, $columnNames)))
);

$varcharWithoutLength = [];
$varcharCharacters = 0;
foreach ($columns as $name => $column) {
    if (strtoupper((string) ($column['type'] ?? 'VARCHAR')) !== 'VARCHAR') {
        continue;
    }
    $length = (int) ($column['length'] ?? 0);
    if ($length <= 0) {
        $varcharWithoutLength[] = (string) $name;
        continue;
    }
    $varcharCharacters += $length;
}
$assert(
    $varcharWithoutLength === [] && $varcharCharacters < 4096,
    'ogni VARCHAR di Residenza ha lunghezza esplicita e la riga resta entro margine sicuro',
    'senza lunghezza: '.implode(', ', $varcharWithoutLength)
);

$indexedTooWide = [];
foreach (Residenza::tablePseudos() as $indexName => $pseudo) {
    foreach ((array) ($pseudo['index'] ?? []) as $columnName) {
        $column = $columns[(string) $columnName] ?? [];
        if (strtoupper((string) ($column['type'] ?? '')) === 'VARCHAR' && (int) ($column['length'] ?? 0) > 191) {
            $indexedTooWide[] = "{$indexName}:{$columnName}";
        }
    }
}
$assert($indexedTooWide === [], 'gli indici VARCHAR di Residenza restano ≤ 191', implode(', ', $indexedTooWide));

$assert(
    strtoupper((string) ($columns['features']['type'] ?? '')) === 'JSON',
    'features è una colonna JSON'
);

echo "FK comune_id\n";
$assert(
    strtoupper((string) ($columns['comune_id']['type'] ?? '')) === 'INT',
    'comune_id è INT (FK verso immobili_comuni)'
);

echo "Schema ResidenzaImmagine\n";
$imgColumns = array_keys(ResidenzaImmagine::getColumns());
$assert(
    array_values(array_intersect(['residenza_id', 'upload', 'titolo', 'position'], $imgColumns)) === ['residenza_id', 'upload', 'titolo', 'position'],
    'ResidenzaImmagine dichiara residenza_id, upload, titolo, position'
);
$imgUpload = ResidenzaImmagine::dataFields()['upload'] ?? null;
$assert(
    is_object($imgUpload)
        && $imgUpload->getSchema('max_size') === 3
        && $imgUpload->getSchema('extensions') === ['png', 'jpg', 'jpeg'],
    'i limiti upload immagini residenza sono png/jpg/jpeg max 3MB'
);
$assert(
    ResidenzaImmagine::firstUploadedFile('["a.jpg"]') === 'a.jpg'
        && ResidenzaImmagine::firstUploadedFile('legacy.jpg') === 'legacy.jpg',
    'firstUploadedFile legge JSON e formato legacy'
);

echo "Immobile.residenza_id\n";
$immobileColumns = Immobile::getColumns();
$assert(
    isset($immobileColumns['residenza_id'])
        && strtoupper((string) ($immobileColumns['residenza_id']['type'] ?? '')) === 'INT',
    'Immobile ha la colonna FK residenza_id (INT)'
);
$assert(
    array_key_exists('ind_residenza', Immobile::tablePseudos()),
    'Immobile indicizza residenza_id'
);

echo "ResidenzaForm::features\n";
$features = \Wonder\Plugin\Immobili\Support\ResidenzaForm::features();
$expectedFeatureIds = [
    'ascensore', 'giardino', 'box_auto', 'domotica', 'fotovoltaico',
    'climatizzazione', 'area_verde', 'videosorveglianza', 'cantina', 'terrazzo',
];
$assert(
    array_keys($features) === $expectedFeatureIds,
    'il catalogo features espone gli id previsti nell\'ordine dichiarato',
    'ottenuti: '.implode(', ', array_keys($features))
);
$assert(
    $features['ascensore'] === 'forms.residenze.features.ascensore',
    'le label delle features passano dalle traduzioni forms.residenze.features.*'
);
$assert(
    \Wonder\Plugin\Immobili\Support\ResidenzaForm::featureIcon('fotovoltaico') !== ''
        && \Wonder\Plugin\Immobili\Support\ResidenzaForm::featureIcon('inesistente') === '',
    'ogni feature nota ha un\'icona; le ignote restituiscono stringa vuota'
);

echo "ResidenzaForm::energyClasses (delega)\n";
$energy = \Wonder\Plugin\Immobili\Support\ResidenzaForm::energyClasses();
$assert(
    isset($energy['A4']) && isset($energy['G']) && ($energy[''] ?? null) === '--',
    'le classi energetiche riusano il catalogo immobili (A4…G)'
);

echo "ResidenzaPresenter::timelineLabel\n";
use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;
$assert(ResidenzaPresenter::timelineLabel(2025, null) === '2025', 'anno senza mese → "2025"');
$assert(ResidenzaPresenter::timelineLabel(2025, 3) === '03/2025', 'anno+mese → "03/2025"');
$assert(ResidenzaPresenter::timelineLabel(2025, 0) === '2025', 'mese 0 → solo anno');
$assert(ResidenzaPresenter::timelineLabel(null, 5) === '', 'anno nullo → stringa vuota');
$assert(ResidenzaPresenter::timelineLabel(0, 5) === '', 'anno 0 → stringa vuota');

echo "ResidenzaPresenter::stato\n";
$assert(
    ResidenzaPresenter::stato(['sold' => 'true'], 2026, 8) === 'venduto',
    'sold prevale su tutto → venduto'
);
$assert(
    ResidenzaPresenter::stato(['stato' => 'completato', 'sold' => 'false'], 2026, 8) === 'completato',
    'override manuale valido rispettato'
);
$assert(
    ResidenzaPresenter::stato(['inizio_anno' => '2027', 'fine_anno' => '2028'], 2026, 8) === 'in_arrivo',
    'oggi prima dell\'inizio → in_arrivo'
);
$assert(
    ResidenzaPresenter::stato(['inizio_anno' => '2025', 'fine_anno' => '2027'], 2026, 8) === 'in_corso',
    'oggi tra inizio e fine → in_corso'
);
$assert(
    ResidenzaPresenter::stato(['inizio_anno' => '2023', 'fine_anno' => '2024'], 2026, 8) === 'completato',
    'oggi dopo la fine → completato'
);

echo "ResidenzaPresenter::imageUrl / imagePreview\n";
$GLOBALS['PATH'] = (object) ['upload' => 'https://cdn.example.test/uploads'];
$assert(
    ResidenzaPresenter::imageUrl('a.jpg') === 'https://cdn.example.test/uploads/immobili/residenze/a.jpg',
    'imageUrl compone l\'URL upload della cartella residenze'
);
$assert(
    (new ResidenzaPresenter())->imagePreview(['upload' => '["a.jpg"]']) === 'https://cdn.example.test/uploads/immobili/residenze/a-620.webp',
    'imagePreview costruisce la variante webp -620 dell\'upload manuale'
);
$assert(
    (new ResidenzaPresenter())->imagePreview(['upload' => '']) === '',
    'imagePreview vuota se non c\'è upload'
);
unset($GLOBALS['PATH']);

echo "ResidenzaResource::formSchema\n";
use Wonder\Plugin\Immobili\Resources\ResidenzaResource;

$residenzaFormFields = ResidenzaResource::formSchema();
$residenzaFormKeys = array_map(
    static fn (object $field): string => property_exists($field, 'name') ? (string) $field->name : '',
    $residenzaFormFields
);
$expectedResidenzaFields = [
    'nome', 'sito_url',
    'inizio_anno', 'inizio_mese', 'fine_anno', 'fine_mese',
    'descrizione_breve', 'descrizione_lunga',
    'indirizzo', 'civico', 'cap', 'comune_id', 'latitudine', 'longitudine', 'zoom',
    'logo', 'images', 'immobili_collegati', 'features',
    'classe_energetica', 'unita_abitative', 'capitolato',
    'stato', 'sold', 'evidence', 'visible', 'position',
];
$assert(
    $residenzaFormKeys === $expectedResidenzaFields,
    'i campi del form residenza coincidono con quelli attesi',
    'ottenuti: '.implode(', ', $residenzaFormKeys)
);

echo "Persistenza form residenza\n";
$residenzaColumns = array_keys(\Wonder\Plugin\Immobili\Models\Residenza::getColumns());
$residenzaRelations = array_keys(ResidenzaResource::repeaterRelations());
$virtualResidenzaFields = ['immobili_collegati'];
$nonPersistable = array_values(array_diff($residenzaFormKeys, $residenzaColumns, $residenzaRelations, $virtualResidenzaFields));
$assert(
    $nonPersistable === [],
    'ogni input del form residenza è colonna, relazione o campo virtuale dichiarato',
    'senza destinazione: '.implode(', ', $nonPersistable)
);
$assert(
    $residenzaRelations === ['images'],
    'images è l\'unica relazione fisica della residenza',
    'relazioni: '.implode(', ', $residenzaRelations)
);
$assert(
    ResidenzaResource::stripRelationInputValues(['nome' => 'X', 'images' => [['id' => '1']], 'immobili_collegati' => ['3']]) === ['nome' => 'X'],
    'stripRelationInputValues rimuove images e il campo virtuale immobili_collegati'
);

echo "ResidenzaResource::deriveStato / normalizeFeatures / sanitizeUrl\n";
$assert(ResidenzaResource::sanitizeUrl('https://ok.test/x') === 'https://ok.test/x', 'URL https valido conservato');
$assert(ResidenzaResource::sanitizeUrl('javascript:alert(1)') === '', 'schema non http/https scartato');
$assert(ResidenzaResource::sanitizeUrl('  ') === '', 'stringa vuota → vuota');
$assert(
    ResidenzaResource::normalizeFeatures(['ascensore', 'inesistente', 'giardino', 'ascensore']) === ['ascensore', 'giardino'],
    'normalizeFeatures tiene solo id noti, unici e nell\'ordine di input'
);
$assert(
    ResidenzaResource::normalizeFeatures('non-array') === [],
    'normalizeFeatures su input non-array → []'
);

echo "ResidenzaResource::linkedImmobiliDiff\n";
$diff = ResidenzaResource::linkedImmobiliDiff(['3', '4', '4'], ['4', '5']);
$assert(
    $diff['attach'] === [3, 4] && $diff['detach'] === [5],
    'linkedImmobiliDiff: attach = selezione completa (idempotente), detach = rimossi; interi unici',
    'attach: '.implode(',', $diff['attach']).' detach: '.implode(',', $diff['detach'])
);

echo "\n";
echo $failures === 0
    ? "OK \u{2014} {$assertions} asserzioni passate\n"
    : "FAIL \u{2014} {$failures}/{$assertions} asserzioni fallite\n";
exit($failures === 0 ? 0 : 1);
