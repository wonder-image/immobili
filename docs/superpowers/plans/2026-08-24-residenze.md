# Reparto Residenze — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere al modulo `wonder-image/immobili` un reparto "Residenze" (cantieri/costruzioni gestiti dall'agenzia) DB-backed, con backend CRUD, frontend lista+scheda, timeline, gallery, capitolato PDF, features, classe energetica e immobili collegati.

**Architecture:** `Residenza` è un Model+Resource fratello di `Immobile` nello stesso pacchetto, che riusa le convenzioni del framework (Model dichiarativi, Resource CRUD auto-discovery da `src/Resources`, `RepeaterRelation` per la gallery, componenti/mappa/layout di Immobili). La relazione con gli immobili è 1→N via FK `residenza_id` sulla tabella `immobili`, gestita dagli hook `afterStore`/`afterUpdate` della Resource. La cover è la prima immagine della gallery; il comune è una FK alla tassonomia `immobili_comuni`; le features sono un catalogo definito in lang.

**Tech Stack:** PHP 8.2, `wonder-image/app` ^2.2 (Model/Resource/UploadSchema/RepeaterRelation/ResourceSchema), MySQL, test = script PHP puri eseguiti con `php tests/<file>.php`.

## Global Constraints

- PHP `^8.2`; `declare(strict_types=1)` non è usato nei file `src/` del modulo (seguire lo stile esistente: niente `declare` in `src/`, sì nei file `tests/`).
- Namespace `Wonder\Plugin\Immobili\`; PSR-4 su `src/`.
- Resource si auto-scoprono da `src/Resources/`; Model da `src/Models/` (`module.json` → `database.models`). Nessuna registrazione manuale.
- Ogni colonna VARCHAR deve avere lunghezza esplicita in `tableSchema()`; colonne testuali libere → `TEXT`; le VARCHAR indicizzate ≤ 191 (utf8mb4). Vincolo verificato dai test.
- Styling frontend: SOLO classi utility di `wonder-image/lib` + token sito + Bootstrap (`btn`, `badge`). NESSUN CSS custom nuovo.
- Contenuti single-language (italiano); nessun modello descrizione it/en.
- Features = catalogo in lang (`forms.residenze.features.*`), salvate come JSON di id. Nessuna tassonomia DB.
- Relazione immobili↔residenza: FK `residenza_id` su `immobili`, 1→N. Nessun pivot.
- Test: estendere progressivamente `tests/residenze.php`; ogni task lo esegue con `php tests/residenze.php` e chiude con `php -l` sui file toccati.
- Migrazioni DB (`php forge update`) richiedono il DB dell'utente: NON eseguibili in questo ambiente. I test coprono solo schema/logica pura, senza DB.
- Commit frequenti, uno per task. Prima del primo commit creare un branch dedicato: `git checkout -b feat/residenze` (siamo su `main`).

---

### Task 1: Model `Residenza`, `ResidenzaImmagine` e colonna `residenza_id` su `Immobile`

**Files:**
- Create: `src/Models/Residenza.php`
- Create: `src/Models/ResidenzaImmagine.php`
- Modify: `src/Models/Immobile.php` (aggiunta `residenza_id`: `dataSchema`, `FK_COLUMNS`, `tablePseudos`)
- Create: `tests/residenze.php`

**Interfaces:**
- Produces:
  - `Wonder\Plugin\Immobili\Models\Residenza` — `public static string $table = 'immobili_residenze'`; metodi `dataSchema(): array`, `tableSchema(): array`, `tablePseudos(): array`, `decorate(array $row): array`.
  - `Wonder\Plugin\Immobili\Models\ResidenzaImmagine` — `public static string $table = 'immobili_residenze_immagini'`; `firstUploadedFile(mixed $stored): string`.
  - `Immobile` guadagna la colonna/campo `residenza_id` (INT nullable, FK `immobili_residenze` SET NULL).

- [ ] **Step 1: Scrivere il test che fallisce** — crea `tests/residenze.php`:

```php
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

echo "\n";
echo $failures === 0
    ? "OK \u{2014} {$assertions} asserzioni passate\n"
    : "FAIL \u{2014} {$failures}/{$assertions} asserzioni fallite\n";
exit($failures === 0 ? 0 : 1);
```

- [ ] **Step 2: Eseguire il test per verificarne il fallimento**

Run: `php tests/residenze.php`
Expected: FAIL — `Class "Wonder\Plugin\Immobili\Models\Residenza" not found`.

- [ ] **Step 3: Creare `src/Models/Residenza.php`**

```php
<?php

namespace Wonder\Plugin\Immobili\Models;

use LogicException;
use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Residenza / cantiere gestito dall'agenzia: struttura fatta costruire o di cui
 * l'agenzia gestisce la maggior parte delle unità. Sempre creata a mano dal
 * backend (nessun feed). Gli immobili collegati puntano qui via `immobili.residenza_id`.
 */
final class Residenza extends Model
{
    public static string $table  = 'immobili_residenze';
    public static string $folder = 'immobili/residenze';
    public static string $icon   = 'bi bi-buildings';

    /** @var array<int, string> Colonne testuali libere → TEXT. */
    private const SQL_TEXT_COLUMNS = [
        'nome', 'sito_url', 'descrizione_breve', 'descrizione_lunga', 'indirizzo',
    ];

    /** @var array<int, string> Colonne file/immagine (filename JSON) → TEXT. */
    private const SQL_FILE_COLUMNS = ['logo', 'capitolato'];

    /** @var array<string, int> */
    private const SQL_VARCHAR_LENGTHS = [
        'code' => 32,
        'slug' => 191,
        'civico' => 32,
        'cap' => 16,
        'comune_nome' => 191,
        'latitudine' => 32,
        'longitudine' => 32,
        'zoom' => 8,
        'classe_energetica' => 16,
        'sold' => 5,
        'stato' => 16,
        'visible' => 5,
        'evidence' => 5,
    ];

    /** @var array<string, string> Default SQL dei flag di pubblicazione. */
    private const SQL_DEFAULTS = [
        'visible'  => 'true',
        'evidence' => 'false',
        'sold'     => 'false',
    ];

    /** @var array<string, string> FK intere verso le tassonomie canoniche. */
    private const FK_COLUMNS = [
        'comune_id' => 'immobili_comuni',
    ];

    public static function tableSchema(): array
    {
        $columns = static::sqlColumnsFromDataSchema();

        foreach ($columns as $column) {
            $name = (string) ($column->name ?? '');

            if (isset(self::SQL_DEFAULTS[$name])) {
                $column->default(self::SQL_DEFAULTS[$name]);
            }

            if (isset(self::FK_COLUMNS[$name])) {
                $column->type('INT')->length(10)->null()
                    ->foreign(self::FK_COLUMNS[$name])->foreignOnDelete('SET NULL');
                continue;
            }

            if (in_array($name, self::SQL_TEXT_COLUMNS, true)
                || in_array($name, self::SQL_FILE_COLUMNS, true)) {
                $column->type('TEXT');
                continue;
            }

            if (strtoupper((string) ($column->schema['type'] ?? 'VARCHAR')) !== 'VARCHAR') {
                continue;
            }

            $length = self::SQL_VARCHAR_LENGTHS[$name] ?? null;

            if ($length === null) {
                throw new LogicException("Lunghezza SQL non definita per immobili_residenze.{$name}");
            }

            $column->length($length);
        }

        return $columns;
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_slug'        => ['index' => 'slug'],
            'ind_visible'     => ['index' => 'visible'],
            'ind_sold'        => ['index' => 'sold'],
            'ind_position'    => ['index' => 'position'],
            'ind_comune'      => ['index' => 'comune_id'],
            'ind_comune_nome' => ['index' => 'comune_nome'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('code')->text()->uniqueCode('res_'),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('slug')->text()->slug(),

            Field::key('logo')->image()->maxSize(3)->extensions(['png', 'jpg', 'jpeg', 'svg', 'webp']),
            Field::key('sito_url')->text()->sanitize(false),

            // Timeline: anno obbligatorio in UI, mese opzionale.
            Field::key('inizio_anno')->number()->decimals(0),
            Field::key('inizio_mese')->number()->decimals(0),
            Field::key('fine_anno')->number()->decimals(0),
            Field::key('fine_mese')->number()->decimals(0),

            Field::key('descrizione_breve')->text()->sanitizeFirst(),
            Field::key('descrizione_lunga')->text()->sanitizeFirst(),

            // Localizzazione: comune da tassonomia (FK), indirizzo libero.
            Field::key('indirizzo')->text()->sanitizeFirst(),
            Field::key('civico')->text(),
            Field::key('cap')->text(),
            Field::key('comune_id')->number()->decimals(0),
            Field::key('comune_nome')->text(),
            Field::key('latitudine')->text(),
            Field::key('longitudine')->text(),
            Field::key('zoom')->text(),

            Field::key('classe_energetica')->text(),
            Field::key('unita_abitative')->number()->decimals(0),
            Field::key('features')->json(),
            Field::key('capitolato')->file()->maxSize(20)->extensions(['pdf']),

            Field::key('sold')->text(),
            Field::key('stato')->text(),
            Field::key('visible')->text(),
            Field::key('evidence')->text(),
            Field::key('position')->number()->decimals(0),
        ];
    }

    public static function decorate(array $row): array
    {
        $slug = (string) ($row['slug'] ?? '');
        $row['url'] = __r('residenze.detail', ['slug' => $slug]);

        return $row;
    }
}
```

- [ ] **Step 4: Creare `src/Models/ResidenzaImmagine.php`**

```php
<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\UploadSchema as Field;

/**
 * Immagine della gallery di una residenza. Caricata a mano dal backend: il
 * framework genera automaticamente webp + varianti responsive all'upload.
 * La prima immagine (per `position`) funge da cover.
 */
final class ResidenzaImmagine extends Model
{
    public static string $table  = 'immobili_residenze_immagini';
    public static string $folder = 'immobili/residenze';
    public static string $icon   = 'bi bi-images';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema(['residenza_id', 'upload', 'titolo', 'position']),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_residenza' => ['index' => 'residenza_id'],
            'ind_position'  => ['index' => 'position'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('residenza_id')->number()->decimals(0),
            Field::key('upload')->image()->maxSize(3)->extensions(['png', 'jpg', 'jpeg']),
            Field::key('titolo')->text()->sanitizeFirst(),
            Field::key('position')->number()->decimals(0),
        ];
    }

    /** Legge il filename upload sia dal JSON corrente sia dal formato legacy. */
    public static function firstUploadedFile(mixed $storedFiles): string
    {
        $files = MediaFileManager::decodeStoredFiles($storedFiles);

        if (isset($files[0])) {
            return $files[0];
        }

        if (!is_string($storedFiles)) {
            return '';
        }

        $storedFiles = trim($storedFiles);

        if ($storedFiles === '') {
            return '';
        }

        $decoded = json_decode($storedFiles, true);

        if (is_string($decoded)) {
            return trim($decoded);
        }

        return json_last_error() === JSON_ERROR_NONE ? '' : $storedFiles;
    }
}
```

- [ ] **Step 5: Modificare `src/Models/Immobile.php` — aggiungere `residenza_id`**

In `dataSchema()`, subito dopo il blocco "Classificazione" (dopo `Field::key('tipologia_id')->number()->decimals(0),`), aggiungere:

```php
            // Residenza di appartenenza (reparto Residenze). FK a immobili_residenze,
            // gestita a mano dal backend delle residenze; il sync non la tocca.
            Field::key('residenza_id')->number()->decimals(0),
```

In `FK_COLUMNS` (la costante privata), aggiungere la voce:

```php
        'residenza_id'      => 'immobili_residenze',
```

In `tablePseudos()`, aggiungere nell'array restituito:

```php
            'ind_residenza'  => ['index' => 'residenza_id'],
```

- [ ] **Step 6: Eseguire il test per verificarlo passato**

Run: `php tests/residenze.php`
Expected: PASS — tutte le asserzioni verdi.

- [ ] **Step 7: Lint**

Run: `php -l src/Models/Residenza.php && php -l src/Models/ResidenzaImmagine.php && php -l src/Models/Immobile.php`
Expected: `No syntax errors detected` per ciascuno.

- [ ] **Step 8: Verificare che lo smoke esistente non regredisca**

Run: `php tests/resource-form.php`
Expected: PASS (l'aggiunta di `residenza_id` non altera il form immobili; `nonPersistableFields` resta vuoto perché `residenza_id` è ora una colonna del Model).

- [ ] **Step 9: Commit**

```bash
git checkout -b feat/residenze
git add src/Models/Residenza.php src/Models/ResidenzaImmagine.php src/Models/Immobile.php tests/residenze.php
git commit -m "feat(residenze): modelli Residenza/ResidenzaImmagine e FK residenza_id su immobili"
```

---

### Task 2: `ResidenzaForm` — catalogo features, opzioni e slug

**Files:**
- Create: `src/Support/ResidenzaForm.php`
- Modify: `tests/residenze.php` (append asserzioni)

**Interfaces:**
- Consumes: `Wonder\Plugin\Immobili\Support\ImmobileForm` (`energyClasses()`, `municipalities()`, `taxonomyLabel()`), `Wonder\Plugin\Immobili\Support\Slug` (`base()`), `Wonder\Plugin\Immobili\Models\{Residenza,Immobile,Comune}`.
- Produces: `Wonder\Plugin\Immobili\Support\ResidenzaForm` con:
  - `text(string $key, ?string $fallback = null): string`
  - `const FEATURE_KEYS: array<string,string>` (id → chiave lang) e `const FEATURE_ICONS: array<string,string>` (id → icona bootstrap)
  - `features(): array<string,string>` (id → label tradotta)
  - `featureIcon(string $id): string`
  - `energyClasses(): array` (delega a ImmobileForm)
  - `municipalities(): array` (delega a ImmobileForm)
  - `immobili(): array<string,string>` (id → "nome — comune" per il multiselect)
  - `comuneNome(string $comuneId): string`
  - `uniqueSlug(string $nome, int|string|null $excludeId = null): string`

- [ ] **Step 1: Scrivere il test che fallisce** — append a `tests/residenze.php`, subito prima del blocco finale `echo "\n";`:

```php
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
```

- [ ] **Step 2: Eseguire il test per verificarne il fallimento**

Run: `php tests/residenze.php`
Expected: FAIL — `Class "Wonder\Plugin\Immobili\Support\ResidenzaForm" not found`.

- [ ] **Step 3: Creare `src/Support/ResidenzaForm.php`**

```php
<?php

namespace Wonder\Plugin\Immobili\Support;

use Throwable;
use Wonder\Plugin\Immobili\Models\Comune;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;

/**
 * Testi, catalogo features e opzioni del form backend delle residenze.
 * Riusa le tassonomie/energia di ImmobileForm e la slugificazione di Slug.
 */
final class ResidenzaForm
{
    /** @var array<string, string> id feature → chiave lang (suffisso). */
    public const FEATURE_KEYS = [
        'ascensore'        => 'ascensore',
        'giardino'         => 'giardino',
        'box_auto'         => 'box_auto',
        'domotica'         => 'domotica',
        'fotovoltaico'     => 'fotovoltaico',
        'climatizzazione'  => 'climatizzazione',
        'area_verde'       => 'area_verde',
        'videosorveglianza'=> 'videosorveglianza',
        'cantina'          => 'cantina',
        'terrazzo'         => 'terrazzo',
    ];

    /** @var array<string, string> id feature → icona Bootstrap. */
    public const FEATURE_ICONS = [
        'ascensore'         => 'bi bi-arrow-down-up',
        'giardino'          => 'bi bi-tree',
        'box_auto'          => 'bi bi-car-front',
        'domotica'          => 'bi bi-house-gear',
        'fotovoltaico'      => 'bi bi-sun',
        'climatizzazione'   => 'bi bi-snow',
        'area_verde'        => 'bi bi-flower1',
        'videosorveglianza' => 'bi bi-camera-video',
        'cantina'           => 'bi bi-box2',
        'terrazzo'          => 'bi bi-brightness-high',
    ];

    public static function text(string $key, ?string $fallback = null): string
    {
        $translationKey = 'forms.residenze.'.$key;

        if (function_exists('__t')) {
            try {
                return (string) __t($translationKey);
            } catch (Throwable) {
                // pageSchema()/labelSchema() sono letti anche prima che le
                // traduzioni del modulo siano disponibili.
            }
        }

        return $fallback ?? $translationKey;
    }

    /** @return array<string, string> id → label tradotta */
    public static function features(): array
    {
        $options = [];

        foreach (self::FEATURE_KEYS as $id => $key) {
            $options[$id] = self::text('features.'.$key);
        }

        return $options;
    }

    public static function featureIcon(string $id): string
    {
        return self::FEATURE_ICONS[$id] ?? '';
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function energyClasses(): array
    {
        return ImmobileForm::energyClasses();
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function municipalities(): array
    {
        return ImmobileForm::municipalities();
    }

    public static function comuneNome(string $comuneId): string
    {
        return ImmobileForm::taxonomyLabel(Comune::class, $comuneId);
    }

    /**
     * Opzioni per il multiselect "Immobili collegati": tutti gli immobili,
     * etichettati con nome + comune. `['' => '--']` se il DB non è disponibile.
     *
     * @return array<string, string>
     */
    public static function immobili(): array
    {
        $options = [];

        try {
            $rows = Immobile::find([]);
        } catch (Throwable) {
            return $options;
        }

        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        if (!is_array($rows)) {
            return $options;
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $id = (string) $row['id'];
            $nome = trim((string) ($row['nome'] ?? ''));
            $comune = trim((string) ($row['comune_nome'] ?? ''));
            $label = $nome !== '' ? $nome : ('#'.$id);

            if ($comune !== '') {
                $label .= ' — '.$comune;
            }

            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * Slug leggibile e univoco nella tabella immobili_residenze. Riusa la base
     * slug generica; l'unicità è verificata contro le residenze (suffisso -2, -3…).
     */
    public static function uniqueSlug(string $nome, int|string|null $excludeId = null): string
    {
        $base = Slug::base([$nome]);
        $base = $base !== '' ? $base : 'residenza';
        $slug = $base;
        $n = 1;

        while (self::slugTaken($slug, $excludeId)) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private static function slugTaken(string $slug, int|string|null $excludeId): bool
    {
        try {
            $row = Residenza::find(['slug' => $slug], 1);
        } catch (Throwable) {
            return false;
        }

        if (!is_array($row) || !isset($row['id'])) {
            return false;
        }

        return $excludeId === null || (int) $row['id'] !== (int) $excludeId;
    }
}
```

- [ ] **Step 4: Eseguire il test per verificarlo passato**

Run: `php tests/residenze.php`
Expected: PASS.

- [ ] **Step 5: Lint**

Run: `php -l src/Support/ResidenzaForm.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add src/Support/ResidenzaForm.php tests/residenze.php
git commit -m "feat(residenze): ResidenzaForm con catalogo features, opzioni e slug univoco"
```

---

### Task 3: `ResidenzaPresenter` — cover, URL immagini, timeline, stato

**Files:**
- Create: `src/Services/ResidenzaPresenter.php`
- Modify: `tests/residenze.php` (append)

**Interfaces:**
- Consumes: `$GLOBALS['PATH']->upload` (base URL upload), `Wonder\Plugin\Immobili\Support\ResidenzaForm`.
- Produces: `Wonder\Plugin\Immobili\Services\ResidenzaPresenter` con metodi statici puri:
  - `timelineLabel(?int $anno, ?int $mese): string` — `''` se `$anno` nullo/≤0; `"2025"` senza mese; `"03/2025"` con mese valido.
  - `stato(array $row, ?int $todayYear = null, ?int $todayMonth = null): string` — id stato: `venduto` se `sold` vero; altrimenti override `stato` valido; altrimenti derivato da timeline vs oggi → `in_arrivo|in_corso|completato` (fallback `in_corso`).
  - `imageUrl(string $file): string` — URL upload assoluto per un filename gallery.
  - `imagePreview(array $row): string` — anteprima webp dell'upload manuale.
  - metodi d'istanza `cover(array $row): string`, `images(array $row): array<int,array{src:string,alt:string}>` (usano `ResidenzaImmagine`, richiedono DB → non testati in isolamento).

- [ ] **Step 1: Scrivere il test che fallisce** — append a `tests/residenze.php` prima del blocco finale:

```php
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
```

- [ ] **Step 2: Eseguire il test per verificarne il fallimento**

Run: `php tests/residenze.php`
Expected: FAIL — `Class "Wonder\Plugin\Immobili\Services\ResidenzaPresenter" not found`.

- [ ] **Step 3: Creare `src/Services/ResidenzaPresenter.php`**

```php
<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Immobili\Models\ResidenzaImmagine;

/**
 * View-model della residenza: cover (prima immagine), URL/anteprime immagini,
 * etichetta timeline e stato derivato. Le classi utility del frontend restano
 * nelle view; qui vivono solo i dati.
 */
final class ResidenzaPresenter
{
    private const FOLDER = 'immobili/residenze';

    /** Etichetta timeline: "" se anno assente, "2025" o "03/2025". */
    public static function timelineLabel(?int $anno, ?int $mese): string
    {
        $anno = (int) $anno;

        if ($anno <= 0) {
            return '';
        }

        $mese = (int) $mese;

        if ($mese >= 1 && $mese <= 12) {
            return sprintf('%02d/%d', $mese, $anno);
        }

        return (string) $anno;
    }

    /**
     * Stato della residenza: venduto | in_arrivo | in_corso | completato.
     *
     * @param array<string, mixed> $row
     */
    public static function stato(array $row, ?int $todayYear = null, ?int $todayMonth = null): string
    {
        if (self::isTrue($row['sold'] ?? '')) {
            return 'venduto';
        }

        $override = strtolower(trim((string) ($row['stato'] ?? '')));

        if (in_array($override, ['in_arrivo', 'in_corso', 'completato'], true)) {
            return $override;
        }

        $todayYear ??= (int) date('Y');
        $todayMonth ??= (int) date('n');
        $today = $todayYear * 100 + $todayMonth;

        $start = self::yearMonth($row['inizio_anno'] ?? null, $row['inizio_mese'] ?? null, 1);
        $end = self::yearMonth($row['fine_anno'] ?? null, $row['fine_mese'] ?? null, 12);

        if ($start !== null && $today < $start) {
            return 'in_arrivo';
        }

        if ($end !== null && $today > $end) {
            return 'completato';
        }

        return 'in_corso';
    }

    /** URL upload assoluto di un filename gallery. */
    public static function imageUrl(string $file): string
    {
        $file = trim($file);

        if ($file === '') {
            return '';
        }

        $base = rtrim((string) (($GLOBALS['PATH']->upload ?? '')), '/');

        return $base.'/'.self::FOLDER.'/'.$file;
    }

    /**
     * Anteprima webp (-620) dell'upload manuale; '' se assente.
     *
     * @param array<string, mixed> $row
     */
    public function imagePreview(array $row): string
    {
        $file = ResidenzaImmagine::firstUploadedFile($row['upload'] ?? '');

        if ($file === '') {
            return '';
        }

        $dot = strrpos($file, '.');
        $stem = $dot === false ? $file : substr($file, 0, $dot);

        return self::imageUrl($stem.'-620.webp');
    }

    /**
     * Cover = prima immagine (per position). '' se la gallery è vuota.
     *
     * @param array<string, mixed> $row
     */
    public function cover(array $row): string
    {
        foreach ($this->galleryRows($row) as $image) {
            $url = $this->imagePreview($image);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Immagini della gallery (src assoluto + alt).
     *
     * @param array<string, mixed> $row
     * @return array<int, array{src: string, alt: string}>
     */
    public function images(array $row): array
    {
        $images = [];

        foreach ($this->galleryRows($row) as $image) {
            $file = ResidenzaImmagine::firstUploadedFile($image['upload'] ?? '');

            if ($file === '') {
                continue;
            }

            $images[] = [
                'src' => self::imageUrl($file),
                'alt' => (string) ($image['titolo'] ?? ''),
            ];
        }

        return $images;
    }

    /**
     * Righe gallery ordinate per position. Richiede il DB.
     *
     * @param array<string, mixed> $row
     * @return array<int, array<string, mixed>>
     */
    private function galleryRows(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);

        if ($id <= 0) {
            return [];
        }

        $rows = ResidenzaImmagine::find(['residenza_id' => $id], null, 'position', 'ASC');

        if (is_array($rows) && isset($rows['id'])) {
            return [$rows];
        }

        return is_array($rows) ? $rows : [];
    }

    private static function yearMonth(mixed $anno, mixed $mese, int $defaultMonth): ?int
    {
        $anno = (int) $anno;

        if ($anno <= 0) {
            return null;
        }

        $mese = (int) $mese;

        if ($mese < 1 || $mese > 12) {
            $mese = $defaultMonth;
        }

        return $anno * 100 + $mese;
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'si', 'sì', 'yes'], true);
    }
}
```

- [ ] **Step 4: Eseguire il test per verificarlo passato**

Run: `php tests/residenze.php`
Expected: PASS.

- [ ] **Step 5: Lint**

Run: `php -l src/Services/ResidenzaPresenter.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add src/Services/ResidenzaPresenter.php tests/residenze.php
git commit -m "feat(residenze): ResidenzaPresenter (cover, immagini, timeline, stato)"
```

---

### Task 4: `ResidenzaResource` — schema, form, tabella, navigazione, permessi e mutate puro

**Files:**
- Create: `src/Resources/ResidenzaResource.php`
- Modify: `tests/residenze.php` (append)

**Interfaces:**
- Consumes: `Wonder\App\Resource`, `Wonder\App\ResourceSchema\{FormField,PageSchema,ApiSchema,PermissionSchema,NavigationSchema,TableColumn,TableLayoutSchema,RepeaterColumn,RepeaterRelation}`, `ResidenzaForm`, `ResidenzaPresenter`, `Residenza`, `ResidenzaImmagine`.
- Produces: `Wonder\Plugin\Immobili\Resources\ResidenzaResource` (auto-discovered). Metodi pubblici usati dai test: `formSchema()`, `formLayoutSchema()`, `repeaterRelations()`, `stripRelationInputValues()`, `mutateRequestValues()`, `mutateFormValues()`, e helper statici `deriveStato()`, `normalizeFeatures()`, `sanitizeUrl()`, `linkedImmobiliDiff()`, `afterStore()`, `afterUpdate()`, `hydrateRepeaterFormValues()`.

> Questo task crea la Resource completa incluse gallery e immobili collegati; è
> unico perché la persistenza (test "ogni input ha destinazione") richiede che
> form, relazione `images` e campo virtuale `immobili_collegati` esistano insieme.

- [ ] **Step 1: Scrivere il test che fallisce** — append a `tests/residenze.php` prima del blocco finale:

```php
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
    ResidenzaResource::stripRelationInputValues(['nome' => 'X', 'images' => [['id' => '1']]]) === ['nome' => 'X'],
    'stripRelationInputValues rimuove la relazione images dal payload tabella'
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
    'linkedImmobiliDiff calcola attach (nuovi) e detach (rimossi) come interi unici',
    'attach: '.implode(',', $diff['attach']).' detach: '.implode(',', $diff['detach'])
);
```

- [ ] **Step 2: Eseguire il test per verificarne il fallimento**

Run: `php tests/residenze.php`
Expected: FAIL — `Class "Wonder\Plugin\Immobili\Resources\ResidenzaResource" not found`.

- [ ] **Step 3: Creare `src/Resources/ResidenzaResource.php`**

```php
<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Models\ResidenzaImmagine;
use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;
use Wonder\Plugin\Immobili\Support\ResidenzaForm;

/**
 * Gestione backend delle residenze (cantieri). Record sempre manuali. Gli
 * immobili collegati sono editati qui via multiselect e memorizzati sulla FK
 * `immobili.residenza_id` (nessun pivot), sincronizzata negli hook after*.
 */
final class ResidenzaResource extends Resource
{
    public static string $model = Residenza::class;

    public static string $orderColumn    = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'residenze';
    }

    public static function icon(): string
    {
        return 'bi bi-buildings';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'residenza',
            'plural_label' => 'residenze',
        ];
    }

    public static function labelSchema(): array
    {
        $labels = [];

        foreach ([
            'nome', 'sito_url', 'inizio_anno', 'inizio_mese', 'fine_anno', 'fine_mese',
            'descrizione_breve', 'descrizione_lunga', 'indirizzo', 'civico', 'cap',
            'comune_id', 'latitudine', 'longitudine', 'zoom', 'logo', 'images',
            'immobili_collegati', 'features', 'classe_energetica', 'unita_abitative',
            'capitolato', 'stato', 'sold', 'evidence', 'visible', 'position', 'image',
        ] as $key) {
            $labels[$key] = ResidenzaForm::text('fields.'.$key);
        }

        return $labels;
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('nome')->text()->required(),
            FormField::key('sito_url')->url(),

            self::yearField('inizio_anno'),
            self::monthField('inizio_mese'),
            self::yearField('fine_anno'),
            self::monthField('fine_mese'),

            FormField::key('descrizione_breve')->textarea(),
            FormField::key('descrizione_lunga')->textarea(),

            FormField::key('indirizzo')->text(),
            FormField::key('civico')->text(),
            FormField::key('cap')->text(),
            FormField::key('comune_id')->selectSearch(ResidenzaForm::municipalities()),
            FormField::key('latitudine')->text(),
            FormField::key('longitudine')->text(),
            FormField::key('zoom')->text(),

            FormField::key('logo')->fileDragDrop('image')->maxSize(3)->extensions(['png', 'jpg', 'jpeg', 'svg', 'webp']),
            self::imageRepeater(),
            FormField::key('immobili_collegati')->selectSearch(ResidenzaForm::immobili(), true),
            FormField::key('features')->selectSearch(ResidenzaForm::features(), true),

            FormField::key('classe_energetica')->select(ResidenzaForm::energyClasses()),
            self::numberField('unita_abitative'),
            FormField::key('capitolato')->fileDragDrop('file')->maxSize(20)->extensions(['pdf']),

            FormField::key('stato')->select(self::statoOptions()),
            FormField::key('sold')->bool()->value('false'),
            FormField::key('evidence')->bool()->value('false'),
            FormField::key('visible')->bool()->value('true'),
            self::numberField('position'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form())->components([
            (new Container())->components([
                self::card([
                    static::getInput('nome')->columnSpan(['default' => 12, 'md' => 8]),
                    static::getInput('sito_url')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('descrizione_breve')->columnSpan(12),
                    static::getInput('descrizione_lunga')->columnSpan(12),
                ]),
                self::card([
                    self::section('timeline'),
                    static::getInput('inizio_anno')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('inizio_mese')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('fine_anno')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('fine_mese')->columnSpan(['default' => 6, 'md' => 3]),
                ]),
                self::card([
                    self::section('location'),
                    static::getInput('indirizzo')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('civico')->columnSpan(['default' => 6, 'md' => 2]),
                    static::getInput('cap')->columnSpan(['default' => 6, 'md' => 4]),
                    static::getInput('comune_id')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('latitudine')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('longitudine')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('zoom')->columnSpan(['default' => 12, 'md' => 6]),
                ]),
                self::card([
                    self::section('media'),
                    static::getInput('logo')->columnSpan(12),
                    static::getInput('images')->columnSpan(12),
                ]),
                self::card([
                    self::section('linked'),
                    static::getInput('immobili_collegati')->columnSpan(12),
                ]),
                self::card([
                    self::section('features'),
                    static::getInput('features')->columnSpan(12),
                ]),
                self::card([
                    self::section('capitolato'),
                    static::getInput('capitolato')->columnSpan(12),
                ]),
            ])->columns(12)->columnSpan(['default' => 12, 'lg' => 9]),
            (new Container())->components([
                self::card([
                    self::section('energy'),
                    static::getInput('classe_energetica')->columnSpan(12),
                    static::getInput('unita_abitative')->columnSpan(12),
                ]),
                self::card([
                    self::section('publish'),
                    static::getInput('stato')->columnSpan(12),
                    static::getInput('sold')->columnSpan(12),
                    static::getInput('evidence')->columnSpan(12),
                    static::getInput('visible')->columnSpan(12),
                    static::getInput('position')->columnSpan(12),
                    (new Submit('upload'))
                        ->label(ResidenzaForm::text('buttons.save'))
                        ->buttonClass('btn btn-dark w-100')
                        ->columnSpan(12),
                ]),
            ])->columns(12)->columnSpan(['default' => 12, 'lg' => 3]),
        ])->columns(12)->gap(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->label('')->size('little'),
            TableColumn::key('image')->image()->formatter(static fn (array $row): string => self::coverCell($row))->label('')->size('little')->link('view'),
            TableColumn::key('nome')->text()->link('view'),
            TableColumn::key('comune_nome')->text()->size('medium'),
            TableColumn::key('inizio_anno')->formatter(static fn (array $row): string => self::timelineCell($row))->label(ResidenzaForm::text('fields.timeline', 'Timeline'))->size('medium'),
            TableColumn::key('sold')->booleanBadge('sold')
                ->badgeOff('Disponibile', 'bi bi-tag', 'primary')
                ->badgeOn('Venduto', 'bi bi-check2-circle', 'dark')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge(true)->size('little'),
            TableColumn::key('actions')->button()->actions(['view', 'edit', 'visible', 'evidence', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title(ResidenzaForm::text('titles.list', 'Residenze'))
            ->buttonAdd(ResidenzaForm::text('titles.create', 'Aggiungi residenza'))
            ->results()
            ->filters()
            ->searchFields(['nome', 'comune_nome', 'indirizzo'])
            ->filterRadio('Stato', 'sold', ['false' => 'Disponibili', 'true' => 'Vendute']);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['view', 'list', 'create', 'store', 'edit', 'update', 'delete'])
            ->titles([
                'create' => ResidenzaForm::text('titles.create', 'Aggiungi residenza'),
                'edit'   => ResidenzaForm::text('titles.edit', 'Modifica residenza'),
            ]);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'edit', 'update', 'delete'], ['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title(ResidenzaForm::text('titles.list', 'Residenze'))
            ->order(20)
            ->authority(['admin', 'immobili_manager']);
    }

    // --- Form helpers -----------------------------------------------------

    /** @param array<int, object|string> $components */
    private static function card(array $components, int|array $span = 12): Card
    {
        return (new Card())->components($components)->columns(12)->columnSpan($span);
    }

    private static function section(string $key): SectionTitle
    {
        return SectionTitle::make(ResidenzaForm::text('sections.'.$key))->level(5)->columnSpan(12);
    }

    private static function numberField(string $key): FormField
    {
        return FormField::key($key)->number()->decimal(0)->decimalSeparator(',')->groupSeparator('');
    }

    private static function yearField(string $key): FormField
    {
        return FormField::key($key)->number()->decimal(0)->groupSeparator('');
    }

    private static function monthField(string $key): FormField
    {
        return FormField::key($key)->number()->decimal(0)->groupSeparator('');
    }

    /** @return array<string, string> */
    private static function statoOptions(): array
    {
        return [
            ''            => ResidenzaForm::text('options.stato.auto', 'Automatico'),
            'in_arrivo'   => ResidenzaForm::text('options.stato.in_arrivo', 'In arrivo'),
            'in_corso'    => ResidenzaForm::text('options.stato.in_corso', 'In corso'),
            'completato'  => ResidenzaForm::text('options.stato.completato', 'Completato'),
        ];
    }

    private static function imageRepeater(): FormField
    {
        return FormField::key('images')
            ->repeater([
                RepeaterColumn::key('id')->hidden(),
                RepeaterColumn::key('preview_url')->hidden(),
                RepeaterColumn::key('upload')
                    ->fileDragDrop('image')
                    ->maxSize(3)
                    ->extensions(['png', 'jpg', 'jpeg'])
                    ->label(ResidenzaForm::text('fields.image'))
                    ->columnSpan(12),
            ])
            ->nested()
            ->repeaterSortable()
            ->repeaterAddLabel(ResidenzaForm::text('buttons.add_image'))
            ->relation(
                RepeaterRelation::make('immobili_residenze_immagini', 'residenza_id')
                    ->positionKey('position')
                    ->softDelete(false)
                    ->model(ResidenzaImmagine::class)
            );
    }

    // --- Table cell formatters -------------------------------------------

    /** @param array<string, mixed> $row */
    private static function coverCell(array $row): string
    {
        $cover = (new ResidenzaPresenter())->cover($row);

        return $cover === '' ? '' : '<img src="'.htmlspecialchars($cover, ENT_QUOTES).'" alt="" class="w-100 h-100 object-fit-cover">';
    }

    /** @param array<string, mixed> $row */
    private static function timelineCell(array $row): string
    {
        $start = ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0));
        $end = ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0));

        if ($start === '' && $end === '') {
            return '';
        }

        return htmlspecialchars(trim($start.' → '.$end, ' →'), ENT_QUOTES);
    }

    // --- Pure request/form transforms (unit-tested) ----------------------

    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    /**
     * Normalizza le features al catalogo noto: solo id validi, unici, in ordine.
     *
     * @return array<int, string>
     */
    public static function normalizeFeatures(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = $raw !== '' ? json_decode($raw, true) : [];
            $raw = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $known = ResidenzaForm::FEATURE_KEYS;
        $result = [];

        foreach ($raw as $value) {
            $id = is_scalar($value) ? trim((string) $value) : '';

            if ($id !== '' && isset($known[$id]) && !in_array($id, $result, true)) {
                $result[] = $id;
            }
        }

        return $result;
    }

    /**
     * Stato canonico derivato (delega al presenter) usato al salvataggio quando
     * l'utente non forza un override.
     */
    public static function deriveStato(array $values): string
    {
        return ResidenzaPresenter::stato($values);
    }

    /**
     * Calcola gli immobili da agganciare/sganciare confrontando la selezione
     * con lo stato corrente.
     *
     * @param array<int, int|string> $selectedIds
     * @param array<int, int|string> $currentIds
     * @return array{attach: array<int,int>, detach: array<int,int>}
     */
    public static function linkedImmobiliDiff(array $selectedIds, array $currentIds): array
    {
        $selected = array_values(array_unique(array_filter(array_map('intval', $selectedIds), static fn (int $id): bool => $id > 0)));
        $current = array_values(array_unique(array_filter(array_map('intval', $currentIds), static fn (int $id): bool => $id > 0)));

        return [
            'attach' => array_values(array_diff($selected, $current)),
            'detach' => array_values(array_diff($current, $selected)),
        ];
    }

    // --- Lifecycle --------------------------------------------------------

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $values['sito_url'] = self::sanitizeUrl((string) ($values['sito_url'] ?? ''));
        $values['features'] = self::normalizeFeatures($values['features'] ?? []);

        foreach (['inizio_anno', 'inizio_mese', 'fine_anno', 'fine_mese', 'unita_abitative', 'position'] as $intKey) {
            $raw = trim((string) ($values[$intKey] ?? ''));
            $values[$intKey] = $raw === '' ? '' : (string) (int) $raw;
        }

        // Comune: denormalizza il nome dalla tassonomia; rimuovi la FK vuota.
        $comuneId = (string) ($values['comune_id'] ?? '');
        $values['comune_nome'] = $comuneId !== '' && (int) $comuneId > 0
            ? ResidenzaForm::comuneNome($comuneId)
            : '';

        if ((int) $comuneId <= 0) {
            unset($values['comune_id']);
        }

        // Flag di pubblicazione normalizzati.
        foreach (['sold', 'evidence', 'visible'] as $flag) {
            $values[$flag] = self::isTrue($values[$flag] ?? '') ? 'true' : 'false';
        }

        // Stato: override valido o derivato dalla timeline.
        $override = strtolower(trim((string) ($values['stato'] ?? '')));
        $values['stato'] = in_array($override, ['in_arrivo', 'in_corso', 'completato'], true)
            ? $override
            : self::deriveStato($values);

        // Slug stabile: generato solo se non esiste già sul record.
        if (empty($oldValues['slug'])) {
            $excludeId = isset($oldValues['id']) ? (int) $oldValues['id'] : null;
            $values['slug'] = ResidenzaForm::uniqueSlug((string) ($values['nome'] ?? ''), $excludeId);
        }

        return $values;
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        // Le features persistite (array/JSON) tornano al form come array di id.
        if (array_key_exists('features', $values)) {
            $values['features'] = self::normalizeFeatures($values['features']);
        }

        // Righe gallery: prepara upload JSON + anteprima per FilePond.
        if (is_array($values['images'] ?? null)) {
            $presenter = new ResidenzaPresenter();
            $values['images'] = array_values(array_map(
                static function (mixed $row) use ($presenter): array {
                    $row = is_array($row) ? $row : [];
                    $file = ResidenzaImmagine::firstUploadedFile($row['upload'] ?? '');
                    $row['upload'] = $file !== ''
                        ? json_encode([$file], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                        : '';
                    $row['preview_url'] = $presenter->imagePreview($row);

                    return $row;
                },
                $values['images']
            ));
        }

        return $values;
    }

    public static function prepareRepeaterRelationRow(
        string $inputName,
        array $payload,
        array $row,
        ?array $existingRow = null,
        string $action = 'store',
        string $context = 'backend'
    ): array {
        if ($inputName === 'images') {
            unset($payload['preview_url']);
        }

        return $payload;
    }

    public static function hydrateRepeaterFormValues(
        array $values,
        int|string|null $parentId = null,
        ?array $post = null,
        ?array $files = null
    ): array {
        $values = parent::hydrateRepeaterFormValues($values, $parentId, $post, $files);

        // In edit pre-seleziona gli immobili già collegati (nessun POST attivo).
        $editing = $parentId !== null && $parentId !== '' && (int) $parentId > 0;
        $posted = is_array($post) && array_key_exists('immobili_collegati', $post);

        if ($editing && !$posted) {
            $values['immobili_collegati'] = self::linkedImmobiliIds((int) $parentId);
        }

        return $values;
    }

    public static function afterStore(object $result, array $values = []): void
    {
        $residenzaId = (int) ($result->insert_id ?? 0);

        if ($residenzaId > 0) {
            self::syncLinkedImmobili($residenzaId, (array) ($_POST['immobili_collegati'] ?? []));
        }
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        self::syncLinkedImmobili((int) $id, (array) ($_POST['immobili_collegati'] ?? []));
    }

    /**
     * Applica la selezione del multiselect alla FK immobili.residenza_id.
     *
     * @param array<int, mixed> $selected
     */
    private static function syncLinkedImmobili(int $residenzaId, array $selected): void
    {
        if ($residenzaId <= 0) {
            return;
        }

        $diff = self::linkedImmobiliDiff($selected, self::linkedImmobiliIds($residenzaId));

        foreach ($diff['attach'] as $immobileId) {
            Immobile::update(['residenza_id' => $residenzaId], $immobileId);
        }

        foreach ($diff['detach'] as $immobileId) {
            Immobile::update(['residenza_id' => ''], $immobileId);
        }
    }

    /** @return array<int, string> id immobili attualmente collegati */
    private static function linkedImmobiliIds(int $residenzaId): array
    {
        $rows = Immobile::find(['residenza_id' => $residenzaId]);

        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $row): string => is_array($row) ? (string) ($row['id'] ?? '') : '',
            $rows
        ), static fn (string $id): bool => $id !== ''));
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'si', 'sì', 'yes'], true);
    }
}
```

> Nota `Immobile::update(['residenza_id' => ''], $id)`: lo staccamento imposta la
> FK a vuoto; il layer `Table::prepare` la normalizza a `NULL` per la colonna INT
> nullable. Se in verifica manuale la FK non si azzera, sostituire con una query
> esplicita `Immobile::query()->Update(Immobile::$table, ['residenza_id' => null], 'id', $id)`.

- [ ] **Step 4: Eseguire il test per verificarlo passato**

Run: `php tests/residenze.php`
Expected: PASS (formSchema, persistenza, deriveStato/normalizeFeatures/sanitizeUrl, linkedImmobiliDiff).

- [ ] **Step 5: Lint**

Run: `php -l src/Resources/ResidenzaResource.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Verifica render form (facoltativa ma consigliata)** — append temporaneo NON necessario; il render completo dipende dal tema. Salta se l'ambiente non ha il tema caricato.

- [ ] **Step 7: Commit**

```bash
git add src/Resources/ResidenzaResource.php tests/residenze.php
git commit -m "feat(residenze): ResidenzaResource CRUD, gallery, immobili collegati"
```

---

### Task 5: Traduzioni it/en

**Files:**
- Create/Modify: `lang/it/forms.json` (namespace `forms.residenze.*`) — o il file lang esistente del modulo; verificare la struttura in `lang/it/`.
- Create/Modify: `lang/en/forms.json`
- Modify: `lang/it/*` e `lang/en/*` per `components.residenze.*` e `pages.residenze.*` (frontend, Task 6/7)

**Interfaces:**
- Consumes: chiavi usate da `ResidenzaForm::text('fields.*'|'sections.*'|'buttons.*'|'titles.*'|'options.stato.*'|'features.*')` e dalle view frontend (`__t('pages.residenze.*')`, `__t('components.residenze.*')`).
- Produces: le traduzioni corrispondenti.

- [ ] **Step 1: Individuare la struttura lang esistente**

Run: `ls lang/it && echo '---' && head -30 lang/it/forms.json 2>/dev/null || head -30 lang/it/*.json`
Expected: elenco dei file lang (es. `forms.json`, `components.json`, `pages.json`) e la loro forma annidata. Le residenze seguono lo stesso file/struttura degli immobili (`forms.immobili.*` → aggiungere `forms.residenze.*`).

- [ ] **Step 2: Aggiungere le chiavi `forms.residenze` in `lang/it`**

Sotto la radice `forms`, accanto a `immobili`, aggiungere l'oggetto `residenze` con almeno:

```json
"residenze": {
  "fields": {
    "nome": "Nome residenza",
    "sito_url": "Link sito",
    "inizio_anno": "Anno inizio",
    "inizio_mese": "Mese inizio",
    "fine_anno": "Anno fine (stimato)",
    "fine_mese": "Mese fine",
    "descrizione_breve": "Descrizione breve",
    "descrizione_lunga": "Descrizione lunga",
    "indirizzo": "Indirizzo",
    "civico": "Civico",
    "cap": "CAP",
    "comune_id": "Comune",
    "latitudine": "Latitudine",
    "longitudine": "Longitudine",
    "zoom": "Zoom mappa",
    "logo": "Logo",
    "image": "Immagine",
    "images": "Galleria",
    "immobili_collegati": "Immobili collegati",
    "features": "Caratteristiche",
    "classe_energetica": "Classe energetica",
    "unita_abitative": "Unità abitative",
    "capitolato": "Capitolato (PDF)",
    "stato": "Stato",
    "sold": "Venduto tutto",
    "evidence": "In evidenza",
    "visible": "Visibile",
    "position": "Ordine",
    "timeline": "Timeline"
  },
  "sections": {
    "timeline": "Timeline",
    "location": "Localizzazione",
    "media": "Media",
    "linked": "Immobili collegati",
    "features": "Caratteristiche",
    "capitolato": "Capitolato",
    "energy": "Energia e unità",
    "publish": "Stato e pubblicazione"
  },
  "buttons": { "save": "Salva", "add_image": "Aggiungi immagine" },
  "titles": { "list": "Residenze", "create": "Aggiungi residenza", "edit": "Modifica residenza" },
  "options": {
    "stato": { "auto": "Automatico", "in_arrivo": "In arrivo", "in_corso": "In corso", "completato": "Completato" }
  },
  "features": {
    "ascensore": "Ascensore",
    "giardino": "Giardino",
    "box_auto": "Box / posto auto",
    "domotica": "Domotica",
    "fotovoltaico": "Fotovoltaico",
    "climatizzazione": "Climatizzazione",
    "area_verde": "Area verde comune",
    "videosorveglianza": "Videosorveglianza",
    "cantina": "Cantina",
    "terrazzo": "Terrazzo"
  }
}
```

- [ ] **Step 3: Aggiungere `pages.residenze` e `components.residenze` in `lang/it`** (per il frontend dei Task 6/7)

```json
"residenze": {
  "list": {
    "title": "Le nostre residenze",
    "seo": { "title": "Residenze", "description": "Le residenze e i cantieri gestiti da noi." },
    "empty": "Nessuna residenza al momento."
  },
  "detail": {
    "back": "Torna alle residenze",
    "visit_site": "Visita il sito",
    "download_capitolato": "Scarica il capitolato",
    "linked": "Immobili in questa residenza",
    "units": "Unità abitative",
    "energy": "Classe energetica",
    "timeline": "Tempistiche"
  },
  "stato": {
    "in_arrivo": "In arrivo",
    "in_corso": "In corso",
    "completato": "Completato",
    "venduto": "Venduto tutto"
  }
}
```

(In `pages` la chiave `residenze` va sotto `pages`; in `components` sotto `components`. Se il modulo usa un unico file per namespace, replicare la stessa struttura annidata usata dagli immobili.)

- [ ] **Step 4: Replicare le stesse chiavi in `lang/en`** con le traduzioni inglesi equivalenti (Residence, Start year, Housing units, Download brochure, "Properties in this development", ecc.).

- [ ] **Step 5: Verificare che i JSON siano validi**

Run: `for f in lang/it/*.json lang/en/*.json; do php -r "json_decode(file_get_contents('$f'), true); echo '$f: '.(json_last_error()===JSON_ERROR_NONE?'OK':json_last_error_msg()).PHP_EOL;"; done`
Expected: `OK` per ogni file.

- [ ] **Step 6: Commit**

```bash
git add lang/it lang/en
git commit -m "feat(residenze): traduzioni it/en (form backend, frontend, features)"
```

---

### Task 6: Route frontend + pagina lista + componenti card/timeline/features

**Files:**
- Modify: `config/routes/route.frontend.php` (gruppo `residenze.`)
- Create: `view/pages/frontend/residenze/list.php`
- Create: `view/components/residenze/card.php`
- Create: `view/components/residenze/timeline.php`
- Create: `view/components/residenze/features.php`

**Interfaces:**
- Consumes: `Immobili::viewPath()`, `Immobili::component()`, `Immobili::layout('main')`, `ResidenzaPresenter`, `Residenza::safeFind()`, `__r('residenze.list'|'residenze.detail')`, `__t('pages.residenze.*'|'components.residenze.*')`, classi utility lib.
- Produces: rotte con nome `residenze.list` e `residenze.detail`; la lista renderizza le card.

- [ ] **Step 1: Aggiungere il gruppo rotte in `config/routes/route.frontend.php`**

Dentro `Route::area('frontend')->response('html')->group(function () { ... })`, dopo il gruppo `immobili.`, aggiungere:

```php
        Route::name('residenze.')
            ->prefix('/residenze')
            ->group(function () {

                // Lista residenze (griglia + timeline).
                Route::get('/', Immobili::viewPath('pages/frontend/residenze/list.php'))
                    ->name('list');

                // Dettaglio residenza per slug (deve restare l'ultima del gruppo).
                Route::get('/{slug}/', Immobili::viewPath('pages/frontend/residenze/detail.php'))
                    ->name('detail');

            });
```

- [ ] **Step 2: Creare il componente `view/components/residenze/timeline.php`**

```php
<?php

/**
 * Timeline anno/mese di una residenza: inizio → fine stimata.
 * Args: ['inizio' => string, 'fine' => string, 'stato' => string]
 * Solo classi utility wonder-image/lib.
 */

$inizio = trim((string) ($args['inizio'] ?? ''));
$fine   = trim((string) ($args['fine'] ?? ''));
$stato  = trim((string) ($args['stato'] ?? ''));

if ($inizio === '' && $fine === '') {
    return;
}

?>
<div class="d-flex a-items-center gap-3 w-100">
    <div class="d-flex d-column a-items-center">
        <span class="text-small tx-muted"><?= e(__t('pages.residenze.detail.timeline')) ?></span>
    </div>
    <div class="d-flex a-items-center gap-2 fw-600">
        <?php if ($inizio !== '') { ?><span class="text"><?= e($inizio) ?></span><?php } ?>
        <span class="tx-muted">→</span>
        <?php if ($fine !== '') { ?><span class="text"><?= e($fine) ?></span><?php } ?>
    </div>
    <?php if ($stato !== '') { ?>
        <span class="badge text-bg-primary tx-upper"><?= e($stato) ?></span>
    <?php } ?>
</div>
```

- [ ] **Step 3: Creare il componente `view/components/residenze/features.php`**

```php
<?php

/**
 * Elenco features (icona + label) di una residenza.
 * Args: ['features' => array<int,string> id]
 */

use Wonder\Plugin\Immobili\Support\ResidenzaForm;

$ids = is_array($args['features'] ?? null) ? $args['features'] : [];

if ($ids === []) {
    return;
}

$labels = ResidenzaForm::features();

?>
<div class="d-grid col-2 col-p-1 gap-3 w-100">
    <?php foreach ($ids as $id) {
        $label = (string) ($labels[$id] ?? '');
        if ($label === '') { continue; }
        $icon = ResidenzaForm::featureIcon((string) $id);
    ?>
        <div class="d-flex a-items-center gap-2 text">
            <?php if ($icon !== '') { ?><i class="<?= e($icon) ?>"></i><?php } ?>
            <span><?= e($label) ?></span>
        </div>
    <?php } ?>
</div>
```

- [ ] **Step 4: Creare il componente `view/components/residenze/card.php`**

```php
<?php

/**
 * Card di una residenza in lista. Solo classi utility wonder-image/lib.
 * Args: ['residenza' => array riga decorata, 'presenter' => ResidenzaPresenter]
 */

use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;

$row = is_array($args['residenza'] ?? null) ? $args['residenza'] : null;

if ($row === null) {
    return;
}

$presenter = $args['presenter'] ?? new ResidenzaPresenter();
$cover = $presenter->cover($row);
$url = (string) ($row['url'] ?? '#');
$nome = (string) ($row['nome'] ?? '');
$comune = (string) ($row['comune_nome'] ?? '');
$breve = (string) ($row['descrizione_breve'] ?? '');
$stato = ResidenzaPresenter::stato($row);
$statoLabel = (string) __t('pages.residenze.stato.'.$stato);
$timeline = trim(
    ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0))
    .' → '.
    ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0)),
    ' →'
);

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($url) ?>">
    <div class="f-3-2 p-r bg-cover o-hidden" style="background-image:url('<?= e($cover) ?>')">
        <span class="p-a badge text-bg-primary tx-upper" style="top:.6rem;left:.6rem"><?= e($statoLabel) ?></span>
    </div>
    <div class="p-4 d-grid gap-2">
        <div class="text fw-700"><?= e($nome) ?></div>
        <?php if ($comune !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($comune) ?></div>
        <?php } ?>
        <?php if ($timeline !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-calendar3"></i> <?= e($timeline) ?></div>
        <?php } ?>
        <?php if ($breve !== '') { ?>
            <div class="text-small mt-1"><?= e($breve) ?></div>
        <?php } ?>
    </div>
</a>
```

- [ ] **Step 5: Creare la pagina lista `view/pages/frontend/residenze/list.php`**

```php
<?php

/**
 * Lista residenze/cantieri: griglia di card ordinate per position.
 */

use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;

$PAGE_KEY = 'residenze.list';

$SEO->title = __t('pages.residenze.list.seo.title');
$SEO->description = __t('pages.residenze.list.seo.description');
$SEO->url = __r($PAGE_KEY);
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    $SEO->url => __t('pages.residenze.list.title'),
];

$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

$rows = Residenza::safeFind(['visible' => 'true', 'deleted' => 'false'], null, 'position', 'ASC');
$rows = is_array($rows) && isset($rows['id']) ? [$rows] : (is_array($rows) ? $rows : []);
$presenter = new ResidenzaPresenter();

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">
        <h1 class="title-big"><?= e(__t('pages.residenze.list.title')) ?></h1>
    </div>
</section>

<section>
    <div class="content">
        <?php if ($rows === []) { ?>
            <p class="text mt-4"><?= e(__t('pages.residenze.list.empty')) ?></p>
        <?php } else { ?>
            <div class="d-grid col-3 col-p-1 gap-5 mt-4">
                <?php foreach ($rows as $row) {
                    Immobili::component('residenze/card', ['residenza' => $row, 'presenter' => $presenter]);
                } ?>
            </div>
        <?php } ?>
    </div>
</section>

<?php \Wonder\View\View::end(); ?>
```

- [ ] **Step 6: Verifica sintassi delle view e della route**

Run: `php -l config/routes/route.frontend.php && php -l view/pages/frontend/residenze/list.php && php -l view/components/residenze/card.php && php -l view/components/residenze/timeline.php && php -l view/components/residenze/features.php`
Expected: `No syntax errors detected` per ciascuno.

- [ ] **Step 7: Regressione smoke**

Run: `php tests/residenze.php && php tests/resource-form.php && php tests/smoke.php`
Expected: PASS su tutti.

- [ ] **Step 8: Commit**

```bash
git add config/routes/route.frontend.php view/pages/frontend/residenze view/components/residenze
git commit -m "feat(residenze): rotte frontend, lista e componenti card/timeline/features"
```

---

### Task 7: Pagina dettaglio residenza

**Files:**
- Create: `view/pages/frontend/residenze/detail.php`

**Interfaces:**
- Consumes: `Residenza::safeFind()`, `ResidenzaPresenter` (`images`, `cover`, `stato`, `timelineLabel`), `Immobile::safeFind(['residenza_id' => id, 'visible' => 'true'])`, `Immobili::component('card'|'map'|'residenze/timeline'|'residenze/features')`, `Dependencies::swiper()`/`fancyapps()`, `ImmobilePresenter` per le card immobili collegati.
- Produces: pagina `residenze.detail`.

- [ ] **Step 1: Creare `view/pages/frontend/residenze/detail.php`**

```php
<?php

/**
 * Dettaglio residenza: hero gallery, descrizione, timeline, features, unità,
 * classe energetica, capitolato, immobili collegati, mappa, link sito esterno.
 */

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Services\ImmobileQuery;
use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Residenza::safeFind(['slug' => $slug, 'visible' => 'true', 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    header('Location: '.__r('residenze.list'), true, 302);
    exit;
}

$presenter = new ResidenzaPresenter();
$images = $presenter->images($row);
$features = is_array($row['features'] ?? null)
    ? $row['features']
    : (array) json_decode((string) ($row['features'] ?? '[]'), true);

$nome = (string) ($row['nome'] ?? '');
$logo = $presenter->imagePreview(['upload' => json_encode([$row['logo'] ? \Wonder\Plugin\Immobili\Models\ResidenzaImmagine::firstUploadedFile($row['logo']) : ''])]);
$sitoUrl = (string) ($row['sito_url'] ?? '');
$stato = ResidenzaPresenter::stato($row);
$statoLabel = (string) __t('pages.residenze.stato.'.$stato);

$inizio = ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0));
$fine = ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0));

$capitolato = \Wonder\Plugin\Immobili\Models\ResidenzaImmagine::firstUploadedFile($row['capitolato'] ?? '');
$capitolatoUrl = $capitolato !== '' ? ResidenzaPresenter::imageUrl($capitolato) : '';

// Immobili collegati (visibili) via FK.
$linkedRows = Immobile::safeFind(['residenza_id' => (int) $row['id'], 'visible' => 'true', 'deleted' => 'false'], null, 'creation', 'DESC');
$linkedRows = is_array($linkedRows) && isset($linkedRows['id']) ? [$linkedRows] : (is_array($linkedRows) ? $linkedRows : []);
$linkedItems = (new ImmobileQuery())->cards($linkedRows);

// Mappa (se coordinate presenti).
$lat = trim((string) ($row['latitudine'] ?? ''));
$lon = trim((string) ($row['longitudine'] ?? ''));
$geojson = ($lat !== '' && $lon !== '')
    ? [[
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [(float) $lon, (float) $lat]],
        'properties' => ['title' => $nome],
    ]]
    : [];

$PAGE_KEY = 'residenze.detail';
$SEO->title = $nome.' - '.$SOCIETY->name;
$SEO->description = mb_substr(strip_tags((string) ($row['descrizione_breve'] ?? $nome)), 0, 160);
$SEO->url = __r('residenze.detail', ['slug' => $slug]);
$SEO->image = $presenter->cover($row);
$SEO->breadcrumb = [
    __r('home') => __t('components.navigation.home'),
    __r('residenze.list') => __t('pages.residenze.list.title'),
    $SEO->url => $nome,
];
$GLOBALS['PAGE_KEY'] = $PAGE_KEY;

if ($images !== []) {
    Dependencies::swiper();
    Dependencies::fancyapps();
}

Immobili::layout('main');

?>

<section class="intro">
    <div class="content">
        <div class="w-100">
            <a href="<?= e(__r('residenze.list')) ?>" class="text-small"><i class="bi bi-arrow-left"></i> <?= e(__t('pages.residenze.detail.back')) ?></a>
        </div>
        <h1 class="title-big mt-3"><?= e($nome) ?></h1>
        <?php if (($row['comune_nome'] ?? '') !== '') { ?>
            <p class="text tx-muted mt-1"><i class="bi bi-geo-alt"></i> <?= e((string) $row['comune_nome']) ?><?php if (($row['indirizzo'] ?? '') !== '') { echo ', '.e((string) $row['indirizzo']); } ?></p>
        <?php } ?>
        <div class="mt-3">
            <?php Immobili::component('residenze/timeline', ['inizio' => $inizio, 'fine' => $fine, 'stato' => $statoLabel]); ?>
        </div>
    </div>
</section>

<?php if ($images !== []) { ?>
<section>
    <div class="content">
        <div class="swiper" id="residenza-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($images as $img) { ?>
                    <div class="swiper-slide o-hidden f-16-9">
                        <a data-fancybox="residenza" href="<?= e($img['src']) ?>">
                            <img src="<?= e($img['src']) ?>" alt="<?= e($img['alt']) ?>" class="bg bg-cover" loading="lazy">
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
<style>#residenza-swiper .swiper-slide{height:auto}#residenza-swiper .swiper-slide .bg{position:absolute;inset:0}</style>
<script>
    new Swiper('#residenza-swiper', { loop: true, speed: 800, autoplay: { delay: 4000 } });
    Fancybox.bind('[data-fancybox="residenza"]', {});
</script>
<?php } ?>

<section>
    <div class="content d-grid col-3 col-p-1 gap-6">
        <div class="col-span-2 col-p-span-1">
            <?php if (($row['descrizione_lunga'] ?? '') !== '') { ?>
                <div class="text"><?= nl2br(e((string) $row['descrizione_lunga'])) ?></div>
            <?php } ?>

            <?php if ($features !== []) { ?>
                <h2 class="subtitle mt-6"><?= e(__t('forms.residenze.sections.features')) ?></h2>
                <div class="mt-3"><?php Immobili::component('residenze/features', ['features' => $features]); ?></div>
            <?php } ?>
        </div>

        <div class="d-grid gap-4 h-fit">
            <?php if ((int) ($row['unita_abitative'] ?? 0) > 0) { ?>
                <div class="p-4 b-r-15 bg-white b-shadow">
                    <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.units')) ?></div>
                    <div class="title"><?= (int) $row['unita_abitative'] ?></div>
                </div>
            <?php } ?>
            <?php if (($row['classe_energetica'] ?? '') !== '') { ?>
                <div class="p-4 b-r-15 bg-white b-shadow">
                    <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.energy')) ?></div>
                    <div class="title"><span class="badge text-bg-success"><?= e((string) $row['classe_energetica']) ?></span></div>
                </div>
            <?php } ?>
            <?php if ($capitolatoUrl !== '') { ?>
                <a href="<?= e($capitolatoUrl) ?>" target="_blank" rel="noopener" class="btn btn-dark w-100"><i class="bi bi-file-earmark-pdf"></i> <?= e(__t('pages.residenze.detail.download_capitolato')) ?></a>
            <?php } ?>
            <?php if ($sitoUrl !== '') { ?>
                <a href="<?= e($sitoUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary w-100"><i class="bi bi-box-arrow-up-right"></i> <?= e(__t('pages.residenze.detail.visit_site')) ?></a>
            <?php } ?>
        </div>
    </div>
</section>

<?php if ($linkedItems !== []) { ?>
<section>
    <div class="content">
        <h2 class="subtitle"><?= e(__t('pages.residenze.detail.linked')) ?></h2>
        <?php Immobili::component('cards-grid', ['immobili' => $linkedItems, 'class' => 'mt-4']); ?>
    </div>
</section>
<?php } ?>

<?php if ($geojson !== []) { ?>
<section>
    <div class="content">
        <?php Immobili::component('map', ['features' => $geojson, 'markerMode' => 'icon']); ?>
    </div>
</section>
<?php } ?>

<?php \Wonder\View\View::end(); ?>
```

> Nota `col-span-2` / `h-fit`: se non presenti tra le utility di `wonder-image/lib`,
> sostituire con la griglia usata altrove nel modulo (es. due `Container` con
> `columnSpan`). Verificare le classi disponibili in
> `node_modules/wonder-image/dist/frontend/head.css` o negli asset del sito
> consumer prima di finalizzare; la regola "solo utility lib" resta vincolante.

- [ ] **Step 2: Verifica sintassi**

Run: `php -l view/pages/frontend/residenze/detail.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add view/pages/frontend/residenze/detail.php
git commit -m "feat(residenze): pagina dettaglio (gallery, timeline, features, immobili collegati, mappa)"
```

---

### Task 8: Link dalla scheda immobile alla residenza

**Files:**
- Modify: `view/pages/frontend/detail.php` (scheda immobile)

**Interfaces:**
- Consumes: `$row['residenza_id']`, `Residenza::safeFind()`, `__r('residenze.detail')`.
- Produces: un link "Parte della residenza: {nome}" nella scheda immobile quando l'immobile è collegato a una residenza visibile.

- [ ] **Step 1: Individuare il punto d'inserimento**

Run: `grep -n "prettyAddress\|class=\"intro\"\|</section>" view/pages/frontend/detail.php | head`
Expected: individuare la `</section>` di chiusura della sezione intro (dopo l'indirizzo, righe ~78-80 nel file corrente).

- [ ] **Step 2: Aggiungere il caricamento della residenza** — subito dopo la riga `$immobile = (new ImmobilePresenter())->present($row);` inserire:

```php
$residenzaLink = null;
$residenzaId = (int) ($row['residenza_id'] ?? 0);
if ($residenzaId > 0) {
    $residenzaRow = \Wonder\Plugin\Immobili\Models\Residenza::safeFind(
        ['id' => $residenzaId, 'visible' => 'true', 'deleted' => 'false'],
        1
    );
    if (is_array($residenzaRow) && isset($residenzaRow['slug'])) {
        $residenzaLink = [
            'nome' => (string) ($residenzaRow['nome'] ?? ''),
            'url'  => __r('residenze.detail', ['slug' => (string) $residenzaRow['slug']]),
        ];
    }
}
```

- [ ] **Step 3: Aggiungere il markup del link** — dentro la sezione `intro`, dopo il blocco dell'indirizzo (`prettyAddress`), prima della chiusura `</div></section>`:

```php
        <?php if ($residenzaLink !== null) { ?>
            <p class="p-r f-start w-100 text mt-2">
                <i class="bi bi-buildings"></i>
                <?= e(__t('components.residenze.card.part_of')) ?>
                <a href="<?= e($residenzaLink['url']) ?>" class="tx-primary fw-600"><?= e($residenzaLink['nome']) ?></a>
            </p>
        <?php } ?>
```

- [ ] **Step 4: Aggiungere la chiave lang `components.residenze.card.part_of`** in `lang/it` (`"part_of": "Parte della residenza:"`) e `lang/en` (`"part_of": "Part of the development:"`).

- [ ] **Step 5: Verifica sintassi e JSON**

Run: `php -l view/pages/frontend/detail.php && for f in lang/it/*.json lang/en/*.json; do php -r "json_decode(file_get_contents('$f'),true); echo '$f: '.(json_last_error()===JSON_ERROR_NONE?'OK':json_last_error_msg()).PHP_EOL;"; done`
Expected: `No syntax errors detected` + `OK` sui JSON.

- [ ] **Step 6: Commit**

```bash
git add view/pages/frontend/detail.php lang/it lang/en
git commit -m "feat(residenze): link alla residenza dalla scheda immobile"
```

---

### Task 9: Verifica finale e documentazione

**Files:**
- Modify: `CHANGELOG.md` (voce reparto Residenze)
- Modify: `module.json` (bump `version`)
- Verify: nessun altro file

**Interfaces:** nessuna nuova.

- [ ] **Step 1: Sweep sintassi su tutto il codice nuovo/modificato**

Run:
```bash
php -l src/Models/Residenza.php && php -l src/Models/ResidenzaImmagine.php \
 && php -l src/Models/Immobile.php && php -l src/Support/ResidenzaForm.php \
 && php -l src/Services/ResidenzaPresenter.php && php -l src/Resources/ResidenzaResource.php \
 && php -l config/routes/route.frontend.php
```
Expected: `No syntax errors detected` per ciascuno.

- [ ] **Step 2: Eseguire l'intera suite di test del modulo**

Run: `php tests/residenze.php && php tests/resource-form.php && php tests/smoke.php && php tests/list-price.php && php tests/pdf.php`
Expected: `OK` / PASS su tutti (nessuna regressione).

- [ ] **Step 3: Validare i JSON lang**

Run: `for f in lang/it/*.json lang/en/*.json; do php -r "json_decode(file_get_contents('$f'),true); echo '$f: '.(json_last_error()===JSON_ERROR_NONE?'OK':json_last_error_msg()).PHP_EOL;"; done`
Expected: `OK` per ogni file.

- [ ] **Step 4: Aggiornare `CHANGELOG.md`** — aggiungere in cima una voce:

```markdown
## [Unreleased]
### Aggiunto
- Reparto **Residenze** (cantieri/costruzioni): Model `Residenza` + gallery
  `ResidenzaImmagine`, `ResidenzaResource` (CRUD backend), frontend
  `/residenze/` e `/residenze/{slug}/` con timeline, features, capitolato PDF,
  classe energetica, unità abitative, mappa e immobili collegati (FK
  `immobili.residenza_id`). Traduzioni it/en. Cover = prima immagine della gallery.
```

- [ ] **Step 5: Bump versione in `module.json`** — aggiornare `"version": "1.0.3"` → `"version": "1.1.0"`.

- [ ] **Step 6: Commit finale**

```bash
git add CHANGELOG.md module.json
git commit -m "chore(residenze): changelog, bump versione modulo a 1.1.0"
```

- [ ] **Step 7: Nota di migrazione (manuale, ambiente utente)** — comunicare all'utente che, sul suo ambiente con DB, va eseguito:

```bash
php forge update
```

per creare le tabelle `immobili_residenze` / `immobili_residenze_immagini` e la colonna `immobili.residenza_id`. `residenza_id` è preservato automaticamente dai sync (il feed fa update parziale e non tocca la colonna).

---

## Self-Review

**Spec coverage** (ogni requisito dello spec → task):
- Nome cantiere/logo/link sito/descrizioni/indirizzo/coordinate → Task 1 (colonne) + Task 4 (form) + Task 6/7 (frontend). ✔
- Timeline anno + mese opzionale → Task 1 (4 colonne) + Task 3 (`timelineLabel`) + Task 6/7 (componente timeline). ✔
- Immobili collegati (FK, multiselect) → Task 1 (`residenza_id`) + Task 4 (`immobili_collegati`, after hooks, hydrate) + Task 7 (render). ✔
- Immagini multiple (gallery, prima=cover) → Task 1 (`ResidenzaImmagine`) + Task 3 (`cover`) + Task 4 (repeater relation) + Task 7 (hero). ✔
- Capitolato PDF → Task 1 (`capitolato`) + Task 4 (form file) + Task 7 (download). ✔
- Classe energetica → Task 1 + Task 2 (`energyClasses` delega) + Task 4 + Task 7. ✔
- Unità abitative (numero) → Task 1 + Task 4 + Task 7. ✔
- Features (catalogo lang, tassonomia condivisa) → Task 2 (`ResidenzaForm::features`) + Task 4 (`normalizeFeatures`) + Task 5 (lang) + Task 7 (render). ✔
- Comune da tassonomia (`comune_id` + `comune_nome`) → Task 1 (FK) + Task 4 (`selectSearch` + denormalizzazione). ✔
- Pagina interna sempre + link esterno opzionale → Task 6/7 (detail sempre generata; CTA `visit_site` se `sito_url`). ✔
- Preservazione `residenza_id` ai sync → automatica (update parziale), documentata Task 9. ✔
- Single-language → nessun modello descrizione it/en. ✔
- Solo utility lib → note nei Task 7. ✔

**Placeholder scan:** nessun TODO/TBD; ogni step con codice mostra il codice. Due note esplicite di verifica ambientale (classi utility lib in Task 7; azzeramento FK in Task 4) indicano il fallback concreto, non lavoro non specificato.

**Type consistency:** `ResidenzaForm::features()`/`FEATURE_KEYS`/`featureIcon()` coerenti tra Task 2/4/6/7. `ResidenzaPresenter::timelineLabel()/stato()/imageUrl()/imagePreview()/cover()/images()` coerenti tra Task 3/4/6/7. `linkedImmobiliDiff()`/`syncLinkedImmobili()`/`linkedImmobiliIds()` coerenti in Task 4. Campo virtuale `immobili_collegati` dichiarato nel form (Task 4), escluso dalla persistenza (Table::prepare) e nel test di persistenza. Relazione fisica unica `images` coerente tra Resource e test.
