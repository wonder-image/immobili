<?php

declare(strict_types=1);

use Wonder\App\ResourceSchema\Input;
use Wonder\App\Theme;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Form\Field as ElementField;
use Wonder\Elements\Form\Form;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Plugin\Immobili\Models\Taxonomy\Categoria;
use Wonder\Plugin\Immobili\Models\Taxonomy\Comune;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Models\Taxonomy\Macrotipologia;
use Wonder\Plugin\Immobili\Models\Taxonomy\Quartiere;
use Wonder\Plugin\Immobili\Models\Taxonomy\QuartiereZona;
use Wonder\Plugin\Immobili\Models\Taxonomy\Tipologia;
use Wonder\Plugin\Immobili\Resources\ImmobileResource;
use Wonder\Plugin\Immobili\Export\IdealistaExporter;
use Wonder\Plugin\Immobili\Catalog\ImmobilePresenter;
use Wonder\Plugin\Immobili\Support\Forms\ImmobileForm;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Smoke test strutturale del form backend degli immobili.
 *
 * Non richiede una connessione al database: verifica soltanto gli schema
 * dichiarativi di Resource, Model e layout.
 *
 * Esecuzione: php tests/resource-form.php
 */

defined('APP_URL') || define('APP_URL', 'https://example.test');
defined('ROOT') || define('ROOT', sys_get_temp_dir());
defined('ASSETS_VERSION') || define('ASSETS_VERSION', 'test');
defined('APP_VERSION') || define('APP_VERSION', '2.2.0');

require __DIR__.'/../vendor/autoload.php';

if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        if (($GLOBALS['IMMOBILI_TEST_TRANSLATIONS_READY'] ?? true) !== true) {
            throw new RuntimeException("Traduzioni non ancora inizializzate: {$key}");
        }

        return $key;
    }
}

$GLOBALS['IMMOBILI_TEST_TRANSLATIONS_READY'] = false;
$earlyPageTitles = (array) ImmobileResource::pageSchema()->get('titles');
$GLOBALS['IMMOBILI_TEST_TRANSLATIONS_READY'] = true;

if (($earlyPageTitles['create'] ?? null) !== 'Aggiungi immobile'
    || ($earlyPageTitles['edit'] ?? null) !== 'Modifica immobile') {
    fwrite(STDERR, "[FAIL] pageSchema deve essere leggibile prima del bootstrap delle traduzioni\n");
    exit(1);
}

// Le select tassonomiche sono intenzionalmente vuote: il test copre la forma
// dello schema e non deve aprire connessioni al database del sito consumer.
$taxonomyOptions = new ReflectionProperty(ImmobileForm::class, 'taxonomyOptions');
$emptyTaxonomyOptions = [
    Categoria::class.'||' => ['' => '--'],
    Macrotipologia::class.'|categoria_id|categoria' => ['' => '--'],
    Tipologia::class.'|macrotipologia_id|macrotipologia' => ['' => '--'],
    Comune::class.'||' => ['' => '--'],
    Quartiere::class.'|comune_id|comune' => ['' => '--'],
    QuartiereZona::class.'|quartiere_id|quartiere' => ['' => '--'],
];
$taxonomyOptions->setValue(null, $emptyTaxonomyOptions);

$expectedFields = [
    'nome',
    'categoria_id',
    'macrotipologia_id',
    'tipologia_id',
    'comune_id',
    'quartiere_id',
    'quartiere_zona_id',
    'strada',
    'indirizzo',
    'civico',
    'cap',
    'note',
    'contratto_id',
    'prezzo',
    'cauzione',
    'durata_contratto_id',
    'superficie',
    'spese_mensili',
    'spese_riscaldamento',
    'reddito',
    'tipo_costruzione_id',
    'stato_costruzione_id',
    'stato_immobile_id',
    'n_camere',
    'n_altre_camere',
    'n_locali',
    'n_bagni',
    'cucina_id',
    'arredamento_id',
    'box_auto_id',
    'n_posti_auto',
    'n_balconi',
    'n_terrazzi',
    'cantina_id',
    'mansarda_id',
    'taverna_id',
    'porta_blindata',
    'allarme',
    'cancello_elettrico',
    'videocitofono',
    'fibra_ottica',
    'camino',
    'n_ascensori',
    'infissi_esterni_id',
    'impianto_tv_id',
    'giardino_privato_id',
    'giardino_condominiale',
    'idromassaggio',
    'piscina',
    'tennis',
    'anno_costruzione',
    'piano',
    'n_livelli',
    'piani_edificio',
    'esposizione_interna',
    'esposizione_esterna',
    'riscaldamento_id',
    'tipo_riscaldamento_id',
    'acqua_calda_id',
    'youtube',
    'virtual_tour',
    'images',
    'floor_plans',
    'legge_classe_energetica_id',
    'classe_energetica',
    'ipe',
    'stato',
    'sold',
    'evidence',
    'visible',
];

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

$formatList = static fn (array $values): string => $values === []
    ? '(nessuno)'
    : implode(', ', array_map(static fn (mixed $value): string => (string) $value, $values));

echo "Tassonomie dipendenti\n";

// Tassonomie canoniche: le opzioni sono indicizzate per id canonico e i filtri
// col genitore contengono l'id del genitore (non più codici del gestionale).
$taxonomyOptions->setValue(null, [
    ...$emptyTaxonomyOptions,
    Macrotipologia::class.'|categoria_id|categoria' => [
        '' => '--',
        '11' => ['name' => 'Macro 1', 'filter' => ['categoria' => '["1"]']],
    ],
    Tipologia::class.'|macrotipologia_id|macrotipologia' => [
        '' => '--',
        '21' => ['name' => 'Tipo 1', 'filter' => ['macrotipologia' => '["11"]']],
    ],
    Quartiere::class.'|comune_id|comune' => [
        '' => '--',
        '31' => ['name' => 'Quartiere 1', 'filter' => ['comune' => '["41"]']],
    ],
    QuartiereZona::class.'|quartiere_id|quartiere' => [
        '' => '--',
        '51' => ['name' => 'Zona 1', 'filter' => ['quartiere' => '["31"]']],
    ],
]);
$validDependencies = ImmobileForm::normalizeDependentValues([
    'categoria_id' => '1',
    'macrotipologia_id' => '11',
    'tipologia_id' => '21',
    'comune_id' => '41',
    'quartiere_id' => '31',
    'quartiere_zona_id' => '51',
    'legge_classe_energetica_id' => '1',
    'classe_energetica' => 'A4',
]);
$invalidDependencies = ImmobileForm::normalizeDependentValues([
    'categoria_id' => '2',
    'macrotipologia_id' => '11',
    'tipologia_id' => '21',
    'comune_id' => '41',
    'quartiere_id' => '31',
    'quartiere_zona_id' => '51',
    'legge_classe_energetica_id' => '1',
    'classe_energetica' => 'A',
]);
$assert(
    ($validDependencies['tipologia_id'] ?? null) === '21'
        && ($validDependencies['quartiere_zona_id'] ?? null) === '51'
        && ($validDependencies['classe_energetica'] ?? null) === 'A4'
        && !array_key_exists('macrotipologia_id', $invalidDependencies)
        && !array_key_exists('tipologia_id', $invalidDependencies)
        && ($invalidDependencies['classe_energetica'] ?? null) === '',
    'le dipendenze valide restano; quelle incoerenti sono azzerate e rimosse come FK vuote'
);
$taxonomyOptions->setValue(null, $emptyTaxonomyOptions);

echo "ImmobileResource::formSchema\n";
$formFields = ImmobileResource::formSchema();
$formKeys = array_map(
    static fn (object $field): string => property_exists($field, 'name') ? (string) $field->name : '',
    $formFields
);

$assert(
    $formKeys === $expectedFields,
    'ordine e insieme dei campi coincidono con il form di riferimento',
    'attesi: '.$formatList($expectedFields)."\n    ottenuti: ".$formatList($formKeys)
);

$forbiddenFields = [
    'customer_id',
    'company_id',
    'descrizione_it',
    'descrizione_en',
];
$presentForbiddenFields = array_values(array_intersect($forbiddenFields, $formKeys));

$assert(
    $presentForbiddenFields === [],
    'customer, company e descrizioni non sono input del form',
    'presenti: '.$formatList($presentForbiddenFields)
);

echo "Persistenza form\n";
$modelSchema = Immobile::getColumns();
$modelColumns = array_keys($modelSchema);
$relations = ImmobileResource::repeaterRelations();
$relationKeys = array_keys($relations);
$virtualFormFields = ['floor_plans'];
$nonPersistableFields = array_values(array_diff($formKeys, $modelColumns, $relationKeys, $virtualFormFields));

$assert(
    $nonPersistableFields === [],
    'ogni input appartiene al Model, a una relazione o a una proiezione virtuale dichiarata',
    'senza destinazione: '.$formatList($nonPersistableFields)
);

$assert(
    $relationKeys === ['images'],
    'images è l\'unica relazione fisica e le planimetrie ne sono una proiezione separata',
    'relazioni: '.$formatList($relationKeys)
);

$strippedRelationValues = ImmobileResource::stripRelationInputValues([
    'nome' => 'Rif. 1',
    'images' => [['id' => '1']],
    'floor_plans' => [['id' => '2']],
]);
$assert(
    $strippedRelationValues === ['nome' => 'Rif. 1'],
    'foto e planimetrie vengono escluse entrambe dal payload della tabella immobili'
);

$nonNestedRelations = [];

foreach ($relations as $key => $relation) {
    $field = $relation['field'] ?? null;
    $context = is_object($field) && method_exists($field, 'get')
        ? (array) ($field->get('context') ?? [])
        : [];

    if (($context['nested'] ?? false) !== true) {
        $nonNestedRelations[] = (string) $key;
    }
}

$assert(
    $relations !== [] && $nonNestedRelations === [],
    'ogni repeater relazionale usa nomi nested',
    'non nested: '.$formatList($nonNestedRelations)
);

echo "Schema SQL immobili\n";
$varcharWithoutLength = [];
$varcharCharacters = 0;

foreach ($modelSchema as $name => $column) {
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
    'ogni VARCHAR ha una lunghezza esplicita e la riga resta entro un margine sicuro',
    'senza lunghezza: '.$formatList($varcharWithoutLength)
        ."\n    caratteri VARCHAR dichiarati: {$varcharCharacters}"
);

$indexedStringsTooWide = [];

foreach (Immobile::tablePseudos() as $indexName => $pseudo) {
    $indexedColumns = (array) ($pseudo['index'] ?? []);

    foreach ($indexedColumns as $columnName) {
        $column = $modelSchema[(string) $columnName] ?? [];

        if (strtoupper((string) ($column['type'] ?? '')) === 'VARCHAR'
            && (int) ($column['length'] ?? 0) > 191) {
            $indexedStringsTooWide[] = "{$indexName}:{$columnName}";
        }
    }
}

$assert(
    $indexedStringsTooWide === [],
    'le colonne VARCHAR indicizzate restano compatibili con utf8mb4',
    'indici oltre 191 caratteri: '.$formatList($indexedStringsTooWide)
);

$longSlug = Slug::base([str_repeat('Immobile con indirizzo molto lungo ', 20)]);
$assert(
    $longSlug !== '' && strlen($longSlug) <= 180,
    'la base dello slug lascia spazio al suffisso entro VARCHAR(191)',
    'lunghezza: '.strlen($longSlug)
);

$compatibilityMediaTypes = [];

foreach (['planimetria', 'virtual_tour', 'visual_tour', 'video'] as $columnName) {
    $compatibilityMediaTypes[$columnName] = strtoupper((string) ($modelSchema[$columnName]['type'] ?? ''));
}

$assert(
    array_values(array_unique($compatibilityMediaTypes)) === ['TEXT']
        && strtoupper((string) ($modelSchema['youtube']['type'] ?? '')) === 'JSON',
    'lo schema media usa TEXT per gli URL compatibili e JSON per youtube',
    'media: '.json_encode($compatibilityMediaTypes)
);

$normalizeMediaFields = new ReflectionMethod(Immobile::class, 'normalizeMediaFields');
$normalizedMedia = $normalizeMediaFields->invoke(null, [
    'youtube' => '["https://youtube.test/new","https://youtube.test/shared","https://youtube.test/new","/embed/local","javascript:alert(1)","data:text/html,test","ftp://media.test/video"]',
    'planimetria' => 'https://cdn.test/legacy-plan.pdf',
    'virtual_tour' => '["https://tour.test/one"]',
    'visual_tour' => null,
    'video' => [],
]);

$assert(
    ($normalizedMedia['youtube'] ?? null) === [
        'https://youtube.test/new',
        'https://youtube.test/shared',
        '/embed/local',
    ]
        && ($normalizedMedia['planimetria'] ?? null) === ['https://cdn.test/legacy-plan.pdf']
        && ($normalizedMedia['virtual_tour'] ?? null) === ['https://tour.test/one']
        && ($normalizedMedia['visual_tour'] ?? null) === []
        && ($normalizedMedia['video'] ?? null) === [],
    'la lettura normalizza media JSON/URL singoli, senza duplicati o schemi non sicuri'
);

$normalizeApiRow = new ReflectionMethod(Immobile::class, 'normalizeApiRow');
$apiMedia = $normalizeApiRow->invoke(null, $normalizedMedia);
$assert(
    ($apiMedia['youtube'] ?? null) === ($normalizedMedia['youtube'] ?? null)
        && ($apiMedia['planimetria'] ?? null) === ($normalizedMedia['planimetria'] ?? null)
        && ($apiMedia['virtual_tour'] ?? null) === ($normalizedMedia['virtual_tour'] ?? null),
    'safeFind preserva gli array media gia decorati'
);

$imageUpload = ImmobileImmagine::dataFields()['upload'] ?? null;
$assert(
    is_object($imageUpload)
        && $imageUpload->getSchema('max_size') === 3
        && $imageUpload->getSchema('extensions') === ['png', 'jpg', 'jpeg'],
    'i limiti upload immagini coincidono lato form e lato server'
);

echo "ImmobileResource::formLayoutSchema\n";
$layout = ImmobileResource::formLayoutSchema();

$assert(
    $layout instanceof Form,
    'il layout radice è Wonder\\Elements\\Form\\Form',
    'tipo ottenuto: '.(is_object($layout) ? $layout::class : get_debug_type($layout))
);

$layoutKeys = [];
$collectLayoutFields = static function (mixed $node) use (&$collectLayoutFields, &$layoutKeys): void {
    if (is_array($node)) {
        foreach ($node as $item) {
            $collectLayoutFields($item);
        }

        return;
    }

    if (!is_object($node)) {
        return;
    }

    if ($node instanceof Input) {
        $layoutKeys[] = (string) $node->name;
    } elseif ($node instanceof ElementField && !$node instanceof Submit) {
        $layoutKeys[] = (string) $node->name;
    }

    if (property_exists($node, 'components') && is_array($node->components)) {
        $collectLayoutFields($node->components);
    }
};

$collectLayoutFields($layout);

$layoutCounts = array_count_values($layoutKeys);
$missingLayoutFields = array_values(array_diff($expectedFields, array_keys($layoutCounts)));
$unexpectedLayoutFields = array_values(array_diff(array_keys($layoutCounts), $expectedFields));
$duplicateLayoutFields = array_keys(array_filter(
    $layoutCounts,
    static fn (int $count): bool => $count !== 1
));

$assert(
    $missingLayoutFields === [] && $unexpectedLayoutFields === [] && $duplicateLayoutFields === [],
    'ogni campo del form compare esattamente una volta nel layout',
    'mancanti: '.$formatList($missingLayoutFields)
        ."\n    inattesi: ".$formatList($unexpectedLayoutFields)
        ."\n    duplicati: ".$formatList($duplicateLayoutFields)
);

echo "Render Bootstrap\n";
Theme::set('bootstrap');
$hadRenderName = array_key_exists('NAME', $GLOBALS);
$oldRenderName = $GLOBALS['NAME'] ?? null;
$hadRenderPath = array_key_exists('PATH', $GLOBALS);
$oldRenderPath = $GLOBALS['PATH'] ?? null;
$GLOBALS['NAME'] = (object) ['folder' => 'immobili'];
$GLOBALS['PATH'] = (object) ['upload' => 'https://cdn.example.test/uploads'];
$html = ResourceFormLayoutRenderer::render($layout, ['footer' => '']);

if ($hadRenderName) {
    $GLOBALS['NAME'] = $oldRenderName;
} else {
    unset($GLOBALS['NAME']);
}

if ($hadRenderPath) {
    $GLOBALS['PATH'] = $oldRenderPath;
} else {
    unset($GLOBALS['PATH']);
}

$assert(
    str_contains($html, 'col-lg-9') && str_contains($html, 'col-lg-3'),
    'la griglia desktop usa la struttura principale 9/3'
);
$assert(
    str_contains($html, 'name="youtube[row_1][url]"')
        && str_contains($html, 'name="virtual_tour[row_1][url]"'),
    'YouTube e Virtual Tour usano repeater nested distinti'
);
$assert(
    str_contains($html, 'name="images[row_1][upload][]"'),
    'il primo upload immagini usa il nome nested atteso'
);
$assert(
    str_contains($html, 'name="floor_plans[row_1][upload][]"'),
    'il primo upload planimetrie usa un repeater nested separato'
);
$assert(
    str_contains($html, 'name="images[row_1][id]"')
        && str_contains($html, 'name="images[row_1][preview_url]"')
        && str_contains($html, 'name="floor_plans[row_1][id]"')
        && str_contains($html, 'name="floor_plans[row_1][preview_url]"')
        && str_contains($html, 'data-wi-dir="https://cdn.example.test/uploads/immobili/"'),
    'entrambi i repeater conservano id, anteprima e directory degli upload esistenti'
);
$assert(
    !str_contains($html, 'name="customer_id"') && !str_contains($html, 'name="company_id"'),
    'il markup non contiene customer_id o company_id'
);
$assert(
    substr_count($html, 'forms.immobili.fields.images') === 1
        && substr_count($html, 'forms.immobili.fields.floor_plans') === 1
        && !str_contains($html, 'forms.immobili.sections.images'),
    'foto e planimetrie hanno card e intestazioni distinte'
);

echo "Comportamento pagina custom\n";
$formViewSource = (string) file_get_contents(__DIR__.'/../view/pages/backend/immobili/form.php');
$assert(
    str_contains($formViewSource, "addEventListener('loaded'")
        && !str_contains($formViewSource, "addEventListener('load'")
        && str_contains($formViewSource, "dispatchEvent(new Event('change'")
        && str_contains($formViewSource, 'field.required = false')
        && str_contains($formViewSource, 'field.disabled = true')
        && str_contains($formViewSource, 'window.setInput(row)')
        && str_contains($formViewSource, "LegacyGlobals::set('NAME', \$NAME)"),
    'la pagina inizializza cascate, modalità feed e nuove righe del repeater'
);
$assert(
    str_contains($formViewSource, 'data-immobili-image-poster')
        && str_contains($formViewSource, 'filepond--image-preview-wrapper')
        && str_contains($formViewSource, 'filepond--image-preview')
        && str_contains($formViewSource, 'filePondRoot.appendChild(previewWrapper)')
        && str_contains($formViewSource, "stylePanelAspectRatio: '4:3'")
        && str_contains($formViewSource, '[name^="floor_plans["][name$="[preview_url]"]')
        && !str_contains($formViewSource, 'contentRow.prepend')
        && str_contains($formViewSource, "'col-md-6', 'col-lg-4', 'col-xxl-3'")
        && str_contains($formViewSource, 'w-100 h-100 object-fit-cover')
        && str_contains($formViewSource, 'wrappedConfirmDelete')
        && str_contains($formViewSource, 'wrappedRemoveRow')
        && str_contains($formViewSource, 'filePond.destroy()')
        && str_contains($formViewSource, "restoredFileInput.dataset.wiValue = ''"),
    'foto e planimetrie hanno poster interno a FilePond e griglia responsive 1/2/3/4'
);

echo "Normalizzazione salvataggio\n";
$feedValues = ImmobileResource::mutateRequestValues(
    ['stato' => 'suspended'],
    'update',
    'backend',
    ['provider' => 'getrix']
);
$assert(
    $feedValues === [
        'provider' => 'getrix',
        'stato' => 'suspended',
        'visible' => 'false',
        'sold' => 'false',
    ] && (ImmobileResource::mutateFormValues($feedValues, 'edit')['provider'] ?? null) === 'getrix',
    'un feed aggiorna lo stato e conserva l’origine server-side per gli eventuali retry'
);

$manualRecordPolicy = new ReflectionMethod(ImmobileResource::class, 'isManualRecord');
$syncMethod = new ReflectionMethod(ImmobileResource::class, 'syncRepeaterRelations');
$hydrateMethod = new ReflectionMethod(ImmobileResource::class, 'hydrateRepeaterFormValues');
$appendMethod = new ReflectionMethod(ImmobileResource::class, 'appendRepeaterRelationsToItem');
$assert(
    $syncMethod->getDeclaringClass()->getName() === ImmobileResource::class
        && $hydrateMethod->getDeclaringClass()->getName() === ImmobileResource::class
        && $appendMethod->getDeclaringClass()->getName() === ImmobileResource::class
        && $manualRecordPolicy->invoke(null, ['provider' => 'manual']) === true
        && $manualRecordPolicy->invoke(null, ['provider' => '']) === true
        && $manualRecordPolicy->invoke(null, ['provider' => 'getrix']) === false
        && $manualRecordPolicy->invoke(null, ['provider' => 'seed']) === false,
    'il lifecycle media custom è attivo e sincronizza soltanto gli immobili manuali'
);

$resourceSourceLines = file($syncMethod->getFileName()) ?: [];
$syncMethodSource = implode('', array_slice(
    $resourceSourceLines,
    $syncMethod->getStartLine() - 1,
    $syncMethod->getEndLine() - $syncMethod->getStartLine() + 1
));
$assert(
    substr_count($syncMethodSource, 'Repeater::syncRelatedRows(') === 1
        && !str_contains($syncMethodSource, 'parent::syncRepeaterRelations'),
    'foto e planimetrie vengono riunite in un solo sync atomico'
);

$roundTripStatus = ImmobileResource::mutateFormValues([
    'stato' => 'rented',
    'visible' => 'true',
    'sold' => 'true',
    'contratto_id' => 'V',
], 'edit');
$assert(
    ($roundTripStatus['stato'] ?? null) === 'rented',
    'lo stato esplicito resta canonico anche se contratto e flag suggeriscono altro'
);

$manualValues = ImmobileResource::mutateRequestValues(
    [
        'stato' => 'purchased',
        'contratto_id' => 'V',
        'cauzione' => '1000',
        'durata_contratto_id' => '1',
        'spese_riscaldamento' => '900',
        'riscaldamento_id' => '255',
        'tipo_riscaldamento_id' => '2',
        'n_camere' => '2',
        'n_altre_camere' => '3',
        'tipologia_id' => '',
        'comune_id' => '',
        'quartiere_id' => '',
        'quartiere_zona_id' => '',
        'youtube' => [
            'row_1' => ['url' => 'https://youtu.be/dQw4w9WgXcQ'],
            'row_2' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'row_3' => ['url' => 'https://www.youtube.com/shorts/abcdefghijk'],
            'row_4' => ['url' => 'https://youtu.be/ZYXwvutsrqp?t=1m30s&list=PL1234567890'],
        ],
        'virtual_tour' => [
            'row_1' => ['url' => 'https://tour.example.test/one?lang=it'],
            'row_2' => ['url' => ''],
            'row_3' => ['url' => 'javascript:alert(1)'],
        ],
    ],
    'update',
    'backend',
    [
        'id' => 7,
        'provider' => 'manual',
        'slug' => 'immobile-esistente',
        'tipologia_nome' => 'Appartamento',
        'comune_nome' => 'Bergamo',
        'attributi' => [],
    ]
);
$assert(
    ($manualValues['n_locali'] ?? null) === 5
        && ($manualValues['cauzione'] ?? null) === ''
        && ($manualValues['durata_contratto_id'] ?? null) === ''
        && ($manualValues['tipo_riscaldamento_id'] ?? null) === ''
        && ($manualValues['visible'] ?? null) === 'true'
        && ($manualValues['sold'] ?? null) === 'true'
        && ($manualValues['youtube'] ?? null) === [
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/abcdefghijk',
            'https://www.youtube.com/embed/ZYXwvutsrqp?start=90&list=PL1234567890',
        ]
        && ($manualValues['virtual_tour'] ?? null) === [
            'https://tour.example.test/one?lang=it',
        ],
    'il salvataggio manuale normalizza dati immobile e liste media multiple'
);

$mediaFormValues = ImmobileResource::mutateFormValues([
    'youtube' => '["https://youtu.be/dQw4w9WgXcQ","https://www.youtube.com/watch?v=dQw4w9WgXcQ"]',
    'virtual_tour' => '"https://tour.example.test/one"',
    'images' => [
        [
            'id' => '1',
            'upload' => 'legacy.jpg',
            'source_url' => '',
            'file' => '',
        ],
        [
            'id' => '2',
            'upload' => '',
            'source_url' => 'https://cdn.example.test/feed.jpg',
            'file' => '',
        ],
    ],
    'floor_plans' => [
        [
            'id' => '3',
            'upload' => '',
            'source_url' => 'https://cdn.example.test/plan.jpg',
            'file' => '',
        ],
    ],
], 'edit');
$assert(
    ($mediaFormValues['youtube'] ?? null) === [
        ['url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ'],
    ]
        && ($mediaFormValues['virtual_tour'] ?? null) === [
            ['url' => 'https://tour.example.test/one'],
        ]
        && ($mediaFormValues['images'][0]['upload'] ?? null) === '["legacy.jpg"]'
        && ($mediaFormValues['images'][0]['preview_url'] ?? null) === ''
        && ($mediaFormValues['images'][1]['preview_url'] ?? null) === 'https://cdn.example.test/feed.jpg'
        && ($mediaFormValues['floor_plans'][0]['preview_url'] ?? null) === 'https://cdn.example.test/plan.jpg',
    'la modifica espande i media e prepara anteprime distinte per foto e planimetrie'
);

$splitImageRows = new ReflectionMethod(ImmobileResource::class, 'splitImageRows');
$splitMedia = $splitImageRows->invoke(null, [
    ['id' => '1', 'tipo' => 'F', 'planimetria' => 'false'],
    ['id' => '2', 'tipo' => 'F', 'planimetria' => 'true'],
    ['id' => '3', 'tipo' => 'P', 'planimetria' => 'false'],
    ['id' => '4', 'tipo' => '', 'planimetria' => ''],
]);
$assert(
    array_column($splitMedia['images'] ?? [], 'id') === ['1', '4']
        && array_column($splitMedia['floor_plans'] ?? [], 'id') === ['2', '3'],
    'la modifica separa anche le planimetrie legacy marcate soltanto con tipo P'
);

$validatedImageRows = new ReflectionMethod(ImmobileResource::class, 'validatedImageRows');
[$validatedRows, $seenImageIds] = $validatedImageRows->invoke(
    null,
    [
        ['id' => '1'],
        ['id' => '99'],
        ['id' => '1'],
        ['id' => '', 'upload' => ['name' => ['nuova.jpg']]],
    ],
    ['1' => true, '2' => true],
    ['2' => true]
);
$assert(
    $validatedRows === [
        ['id' => '1'],
        ['id' => '', 'upload' => ['name' => ['nuova.jpg']]],
    ]
        && $seenImageIds === ['2' => true, '1' => true],
    'il sync rifiuta ID estranei o duplicati ma conserva i nuovi upload'
);

$existingImage = ImmobileResource::prepareRepeaterRelationRow(
    'images',
    ['id' => '9', 'preview_url' => 'https://cdn.example.test/preview.jpg'],
    ['id' => '9', 'upload' => ['name' => ['']]],
    ['id' => '9', 'tipo' => 'P', 'planimetria' => 'true', 'source_url' => 'remote.jpg', 'file' => 'local.jpg']
);
$existingFloorPlan = ImmobileResource::prepareRepeaterRelationRow(
    'floor_plans',
    [
        'id' => '10',
        'tipo' => 'F',
        'planimetria' => 'false',
        'preview_url' => 'https://cdn.example.test/plan.jpg',
    ],
    ['id' => '10', 'upload' => ['name' => ['']]],
    ['id' => '10', 'tipo' => 'F', 'planimetria' => 'false', 'source_url' => 'plan.jpg']
);
$assert(
    ($existingImage['tipo'] ?? null) === 'F'
        && ($existingImage['planimetria'] ?? null) === 'false'
        && ($existingFloorPlan['tipo'] ?? null) === 'P'
        && ($existingFloorPlan['planimetria'] ?? null) === 'true'
        && !array_key_exists('source_url', $existingImage)
        && !array_key_exists('file', $existingImage)
        && !array_key_exists('preview_url', $existingImage)
        && !array_key_exists('preview_url', $existingFloorPlan),
    'il salvataggio classifica foto e planimetrie lato server senza sovrascrivere i file esistenti'
);

echo "URL immagini manuali\n";
$assert(
    ImmobileImmagine::firstUploadedFile('["manuale.jpg"]') === 'manuale.jpg'
        && ImmobileImmagine::firstUploadedFile('legacy.jpg') === 'legacy.jpg',
    'il filename upload viene letto sia dal JSON corrente sia dal formato legacy'
);

$hadPath = array_key_exists('PATH', $GLOBALS);
$oldPath = $GLOBALS['PATH'] ?? null;
$GLOBALS['PATH'] = (object) ['upload' => 'https://cdn.example.test/uploads'];
$imageEntry = new ReflectionMethod(ImmobilePresenter::class, 'imageEntry');
$imagePresenter = new ImmobilePresenter();
$presentedImage = $imageEntry->invoke($imagePresenter, [
    'upload' => '["manuale.jpg"]',
    'source_url' => '',
    'file' => '',
    'planimetria' => 'false',
]);
$manualImagePreview = $imagePresenter->imagePreview([
    'upload' => '["manuale.jpg"]',
    'source_url' => '',
    'file' => '',
]);
$feedImagePreview = $imagePresenter->imagePreview([
    'upload' => '',
    'source_url' => 'https://cdn.example.test/feed.jpg',
    'file' => '',
]);
$processedFeedPreview = $imagePresenter->imagePreview([
    'upload' => '',
    'source_url' => '',
    'file' => 'immobili/feed.jpg',
    'resized' => 'true',
]);
$idealistaImageUrl = new ReflectionMethod(IdealistaExporter::class, 'imageUrl');
$exportedImage = $idealistaImageUrl->invoke(new IdealistaExporter(), [
    'upload' => '["manuale.jpg"]',
    'source_url' => '',
    'file' => '',
]);

if ($hadPath) {
    $GLOBALS['PATH'] = $oldPath;
} else {
    unset($GLOBALS['PATH']);
}

$assert(
    ($presentedImage['src'] ?? null) === 'https://cdn.example.test/uploads/immobili/manuale.jpg'
        && $manualImagePreview === 'https://cdn.example.test/uploads/immobili/manuale-620.webp'
        && $feedImagePreview === 'https://cdn.example.test/feed.jpg'
        && $processedFeedPreview === 'https://cdn.example.test/uploads/immobili/feed-620.webp'
        && $exportedImage === 'https://cdn.example.test/uploads/immobili/manuale.jpg',
    'presenter ed exporter costruiscono URL validi per upload e anteprime feed'
);

echo "\n";
echo $failures === 0
    ? "OK \u{2014} {$assertions} asserzioni passate\n"
    : "FAIL \u{2014} {$failures}/{$assertions} asserzioni fallite\n";

exit($failures === 0 ? 0 : 1);
