# Riorganizzazione su due reparti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendere speculari i reparti Immobili e Residenze dentro `wonder-image/immobili`, sciogliere `src/Services/`, unificare i componenti card e rimuovere sei duplicazioni.

**Architecture:** Prima si spostano le classi senza cambiare logica (un commit isolato e facile da rileggere), poi si deduplica dentro la struttura definitiva, poi si interviene sulle view. Ogni task chiude con la suite completa verde.

**Tech Stack:** PHP 8.2, `wonder-image/app` ^2.2, PSR-4 `Wonder\Plugin\Immobili\` → `src/`, test come script PHP standalone (nessun PHPUnit).

Spec di riferimento: `docs/superpowers/specs/2026-08-26-riorganizzazione-due-reparti-design.md`

## Global Constraints

- **Le URL non cambiano mai**: `/api/immobili/{sync,images,seed,residenze-seed,reindex,search}/` e tutte le route frontend restano identiche. Sono contratto verso i cron dei siti e il push di Gestim.
- **I nomi delle route non cambiano**: `immobili.list`, `immobili.detail`, `immobili.sold`, `residenze.list`, `residenze.detail`, `immobile.*`, `api.immobili.*`.
- **Le chiavi di traduzione non cambiano** (salvo le aggiunte esplicite del Task 12).
- **Nessuna migrazione DB**: `tableSchema()` e `dataSchema()` di ogni Model restano intatti.
- **Namespace stabili**: `Wonder\Plugin\Immobili\Models\Immobile`, `...\Models\Residenza` e tutto `...\Resources\*` NON si spostano.
- **Stile**: solo classi utility di `wonder-image/lib`, nessun CSS custom nuovo, nessun colore hardcoded.
- **Ogni stringa a video** passa da `__t()`; ogni URL da `__r()`.
- **Baseline test da preservare**: 6 suite verdi — smoke 98 asserzioni, residenze 42, resource-form 36, pdf 64, energy-scale e list-price "ALL PASS".

---

### Task 1: Runner unico dei test

Serve un solo comando da ripetere a ogni task. Oggi le sei suite si lanciano a mano una per una. Tutte e sei terminano già con `exit($failures === 0 ? 0 : 1)`, quindi l'exit code è affidabile e al runner basta propagarlo.

**Files:**
- Create: `tests/run.sh`

**Interfaces:**
- Produces: `bash tests/run.sh` — esce 0 se tutte le suite passano, 1 se almeno una fallisce. Usato da ogni task successivo.

- [ ] **Step 1: Creare il runner**

```bash
cat > tests/run.sh <<'SH'
#!/usr/bin/env bash
# Esegue tutte le suite del modulo. Nessuna richiede database.
# Uso:  bash tests/run.sh
set -u

cd "$(dirname "$0")/.."

suites=(smoke energy-scale list-price residenze resource-form pdf)
failed=0

for suite in "${suites[@]}"; do
    printf '\n=== %s ===\n' "$suite"
    if ! php "tests/${suite}.php"; then
        printf '!!! FALLITA: %s\n' "$suite"
        failed=1
    fi
done

printf '\n'
if [ "$failed" -eq 0 ]; then
    echo "TUTTE LE SUITE PASSATE"
else
    echo "ALMENO UNA SUITE FALLITA"
fi

exit "$failed"
SH
chmod +x tests/run.sh
```

- [ ] **Step 2: Verificare che la baseline sia verde**

Run: `bash tests/run.sh | tail -3`
Expected: `TUTTE LE SUITE PASSATE`

Le suite stampano `OK — N asserzioni passate`. I numeri della baseline da ritrovare a ogni task: smoke **98**, residenze **42**, resource-form **36**, pdf **64**; `energy-scale` e `list-price` stampano `ALL PASS`.

- [ ] **Step 3: Verificare che il runner intercetti davvero un fallimento**

```bash
printf '<?php exit(1);' > tests/_tmp.php
sed -i '' 's/suites=(smoke/suites=(_tmp smoke/' tests/run.sh
bash tests/run.sh > /dev/null 2>&1; echo "exit=$?"
sed -i '' 's/suites=(_tmp smoke/suites=(smoke/' tests/run.sh
rm tests/_tmp.php
```

Expected: `exit=1`

- [ ] **Step 4: Commit**

```bash
git add tests/run.sh
git commit -m "test(immobili): runner unico delle suite del modulo"
```

---

### Task 2: Riorganizzazione di src/ (solo spostamenti)

Nessun cambio di logica: solo `git mv`, namespace e import. Isolato in un commit perché è l'unico task il cui diff va riletto a colpo d'occhio.

**Files:**
- Move: `src/Services/ImmobilePresenter.php` → `src/Catalog/ImmobilePresenter.php`
- Move: `src/Services/ImmobileQuery.php` → `src/Catalog/ImmobileQuery.php`
- Move: `src/Services/ResidenzaPresenter.php` → `src/Catalog/ResidenzaPresenter.php`
- Move: `src/Services/ImageProcessor.php` → `src/Media/ImageProcessor.php`
- Move: `src/Services/FeedSyncService.php` → `src/Sync/FeedSyncService.php`
- Move: `src/Services/SyncApiUser.php` → `src/Sync/SyncApiUser.php`
- Move: `src/Services/ImmobileSeeder.php` → `src/Seeding/ImmobileSeeder.php`
- Move: `src/Services/ResidenzaSeeder.php` → `src/Seeding/ResidenzaSeeder.php`
- Move: `src/Services/IdealistaExporter.php` → `src/Export/IdealistaExporter.php`
- Move: `src/Models/{Categoria,Macrotipologia,Tipologia,Regione,Provincia,Comune,Quartiere,QuartiereZona}.php` → `src/Models/Taxonomy/`
- Move: `src/Models/{FeedSource,SyncLog,Settings}.php` → `src/Models/System/`
- Move: `src/Support/{ImmobileForm,ResidenzaForm}.php` → `src/Support/Forms/`
- Modify: ogni file con `use Wonder\Plugin\Immobili\{Services,Models,Support}\...`

**Interfaces:**
- Produces: i namespace definitivi usati da tutti i task successivi —
  `Wonder\Plugin\Immobili\Catalog\{ImmobilePresenter,ImmobileQuery,ResidenzaPresenter}`,
  `...\Media\ImageProcessor`, `...\Sync\{FeedSyncService,SyncApiUser}`,
  `...\Seeding\{ImmobileSeeder,ResidenzaSeeder}`, `...\Export\IdealistaExporter`,
  `...\Models\Taxonomy\{Categoria,Macrotipologia,Tipologia,Regione,Provincia,Comune,Quartiere,QuartiereZona}`,
  `...\Models\System\{FeedSource,SyncLog,Settings}`,
  `...\Support\Forms\{ImmobileForm,ResidenzaForm}`.
- Invariati: `...\Models\{Immobile,ImmobileImmagine,ImmobileDescrizione,Residenza}`, `...\Resources\*`, `...\Support\{Slug,Taxonomy,EnergyScale,AttributeCatalog}`, `...\Feed\*`, `...\Pdf\*`.

- [ ] **Step 1: Spostare i file con git mv**

```bash
mkdir -p src/Catalog src/Media src/Sync src/Seeding src/Export src/Models/Taxonomy src/Models/System src/Support/Forms

git mv src/Services/ImmobilePresenter.php  src/Catalog/ImmobilePresenter.php
git mv src/Services/ImmobileQuery.php      src/Catalog/ImmobileQuery.php
git mv src/Services/ResidenzaPresenter.php src/Catalog/ResidenzaPresenter.php
git mv src/Services/ImageProcessor.php     src/Media/ImageProcessor.php
git mv src/Services/FeedSyncService.php    src/Sync/FeedSyncService.php
git mv src/Services/SyncApiUser.php        src/Sync/SyncApiUser.php
git mv src/Services/ImmobileSeeder.php     src/Seeding/ImmobileSeeder.php
git mv src/Services/ResidenzaSeeder.php    src/Seeding/ResidenzaSeeder.php
git mv src/Services/IdealistaExporter.php  src/Export/IdealistaExporter.php

for m in Categoria Macrotipologia Tipologia Regione Provincia Comune Quartiere QuartiereZona; do
    git mv "src/Models/${m}.php" "src/Models/Taxonomy/${m}.php"
done

for m in FeedSource SyncLog Settings; do
    git mv "src/Models/${m}.php" "src/Models/System/${m}.php"
done

git mv src/Support/ImmobileForm.php  src/Support/Forms/ImmobileForm.php
git mv src/Support/ResidenzaForm.php src/Support/Forms/ResidenzaForm.php

rmdir src/Services
```

- [ ] **Step 2: Aggiornare la dichiarazione namespace dentro i file spostati**

```bash
cd /Users/andreamarinoni/Developer/packages/immobili

sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Services;$/namespace Wonder\\Plugin\\Immobili\\Catalog;/' \
    src/Catalog/ImmobilePresenter.php src/Catalog/ImmobileQuery.php src/Catalog/ResidenzaPresenter.php

sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Services;$/namespace Wonder\\Plugin\\Immobili\\Media;/'  src/Media/ImageProcessor.php
sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Services;$/namespace Wonder\\Plugin\\Immobili\\Sync;/'   src/Sync/FeedSyncService.php src/Sync/SyncApiUser.php
sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Services;$/namespace Wonder\\Plugin\\Immobili\\Seeding;/' src/Seeding/ImmobileSeeder.php src/Seeding/ResidenzaSeeder.php
sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Services;$/namespace Wonder\\Plugin\\Immobili\\Export;/'  src/Export/IdealistaExporter.php

sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Models;$/namespace Wonder\\Plugin\\Immobili\\Models\\Taxonomy;/' src/Models/Taxonomy/*.php
sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Models;$/namespace Wonder\\Plugin\\Immobili\\Models\\System;/'   src/Models/System/*.php
sed -i '' 's/^namespace Wonder\\Plugin\\Immobili\\Support;$/namespace Wonder\\Plugin\\Immobili\\Support\\Forms;/'  src/Support/Forms/*.php
```

- [ ] **Step 3: Aggiornare tutti gli import nel modulo**

```bash
cd /Users/andreamarinoni/Developer/packages/immobili
files=$(grep -rl 'Wonder\\Plugin\\Immobili\\' src http view config tests context.php)

for f in $files; do
  sed -i '' \
    -e 's/Immobili\\Services\\ImmobilePresenter/Immobili\\Catalog\\ImmobilePresenter/g' \
    -e 's/Immobili\\Services\\ImmobileQuery/Immobili\\Catalog\\ImmobileQuery/g' \
    -e 's/Immobili\\Services\\ResidenzaPresenter/Immobili\\Catalog\\ResidenzaPresenter/g' \
    -e 's/Immobili\\Services\\ImageProcessor/Immobili\\Media\\ImageProcessor/g' \
    -e 's/Immobili\\Services\\FeedSyncService/Immobili\\Sync\\FeedSyncService/g' \
    -e 's/Immobili\\Services\\SyncApiUser/Immobili\\Sync\\SyncApiUser/g' \
    -e 's/Immobili\\Services\\ImmobileSeeder/Immobili\\Seeding\\ImmobileSeeder/g' \
    -e 's/Immobili\\Services\\ResidenzaSeeder/Immobili\\Seeding\\ResidenzaSeeder/g' \
    -e 's/Immobili\\Services\\IdealistaExporter/Immobili\\Export\\IdealistaExporter/g' \
    -e 's/Immobili\\Models\\Categoria/Immobili\\Models\\Taxonomy\\Categoria/g' \
    -e 's/Immobili\\Models\\Macrotipologia/Immobili\\Models\\Taxonomy\\Macrotipologia/g' \
    -e 's/Immobili\\Models\\Tipologia/Immobili\\Models\\Taxonomy\\Tipologia/g' \
    -e 's/Immobili\\Models\\Regione/Immobili\\Models\\Taxonomy\\Regione/g' \
    -e 's/Immobili\\Models\\Provincia/Immobili\\Models\\Taxonomy\\Provincia/g' \
    -e 's/Immobili\\Models\\Comune/Immobili\\Models\\Taxonomy\\Comune/g' \
    -e 's/Immobili\\Models\\QuartiereZona/Immobili\\Models\\Taxonomy\\QuartiereZona/g' \
    -e 's/Immobili\\Models\\Quartiere;/Immobili\\Models\\Taxonomy\\Quartiere;/g' \
    -e 's/Immobili\\Models\\FeedSource/Immobili\\Models\\System\\FeedSource/g' \
    -e 's/Immobili\\Models\\SyncLog/Immobili\\Models\\System\\SyncLog/g' \
    -e 's/Immobili\\Models\\Settings/Immobili\\Models\\System\\Settings/g' \
    -e 's/Immobili\\Support\\ImmobileForm/Immobili\\Support\\Forms\\ImmobileForm/g' \
    -e 's/Immobili\\Support\\ResidenzaForm/Immobili\\Support\\Forms\\ResidenzaForm/g' \
    "$f"
done
```

**Attenzione all'ordine**: `QuartiereZona` va sostituito **prima** di `Quartiere`, altrimenti la seconda regola corrompe la prima. Per lo stesso motivo `Quartiere` è ancorato al `;` finale. Verificare subito dopo:

```bash
grep -rn 'Taxonomy\\Taxonomy\|Taxonomy\\QuartiereZona\\|System\\System' src http view config tests
```

Expected: nessun risultato.

- [ ] **Step 4: Rigenerare l'autoload e verificare la sintassi**

```bash
composer dump-autoload
find src http view config tests -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
```

Expected: `SINTASSI OK`

- [ ] **Step 5: Verificare che non resti nessun riferimento ai namespace vecchi**

```bash
grep -rn 'Immobili\\Services\\' src http view config tests context.php || echo "NESSUN RESIDUO"
```

Expected: `NESSUN RESIDUO`

- [ ] **Step 6: Eseguire la suite**

Run: `bash tests/run.sh | tail -3`
Expected: `TUTTE LE SUITE PASSATE`, con lo stesso numero di asserzioni della baseline (98/42/36/64).

- [ ] **Step 7: Commit**

```bash
git add -A src http view config tests context.php
git commit -m "refactor(immobili): scioglie Services/, sottocartella Taxonomy/System/Forms

Solo spostamenti e namespace: nessun cambio di logica."
```

---

### Task 3: Slug parametrico sul modello

Oggi `Slug` interroga `Immobile` per costante e `ResidenzaForm::uniqueSlug()` riscrive la stessa logica su `Residenza`.

**Files:**
- Modify: `src/Support/Slug.php`
- Modify: `src/Support/Forms/ResidenzaForm.php` (rimuove `uniqueSlug()` e `slugTaken()`)
- Modify: `src/Resources/ResidenzaResource.php:409`
- Modify: `src/Seeding/ResidenzaSeeder.php:115`
- Modify: `http/api/task/reindex.php:76`
- Test: `tests/smoke.php`

**Interfaces:**
- Consumes: namespace del Task 2.
- Produces:
```php
Slug::base(array $parts, string $fallback = 'immobile'): string
Slug::unique(string $base, string $modelClass = Immobile::class, int|string|null $excludeId = null): string
Slug::fromParts(array $parts, string $modelClass = Immobile::class, int|string|null $excludeId = null, string $fallback = 'immobile'): string
Slug::fromRow(array $row, int|string|null $excludeId = null): string   // invariata, solo immobili
```

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, subito dopo la riga `$assert(... === 'immobile', "vuoto => fallback 'immobile'");` (riga 47), aggiungere:

```php
$assert(\Wonder\Plugin\Immobili\Support\Slug::base([''], 'residenza') === 'residenza', "vuoto + fallback esplicito => 'residenza'");
$assert(\Wonder\Plugin\Immobili\Support\Slug::base(['Corte Verde'], 'residenza') === 'corte-verde', "il fallback non interferisce quando c'è testo");

echo "Slug parametrico sul modello\n";
$slugReflection = new ReflectionMethod(\Wonder\Plugin\Immobili\Support\Slug::class, 'unique');
$slugParams = $slugReflection->getParameters();
$assert(count($slugParams) === 3, "Slug::unique accetta base, modelClass, excludeId");
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
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A2 'Slug parametrico'`
Expected: FAIL — `✗ Slug::unique accetta base, modelClass, excludeId` (oggi ne accetta 2) e `✗ ResidenzaForm::uniqueSlug è stata rimossa`.

- [ ] **Step 3: Rendere Slug parametrica**

In `src/Support/Slug.php` sostituire `base()`, `unique()` e `taken()`, e aggiungere `fromParts()`:

```php
    /**
     * Base leggibile dello slug a partire dai pezzi (tipologia, via, comune, …).
     * `$fallback` è il valore usato quando i pezzi sono tutti vuoti: 'immobile'
     * per gli immobili, 'residenza' per le residenze.
     *
     * @param array<int, mixed> $parts
     */
    public static function base(array $parts, string $fallback = 'immobile'): string
    {
        $parts = array_map(static fn ($p): string => trim((string) $p), $parts);
        $text = trim(implode(' ', array_filter($parts)));

        // `Wonder\Support\Text\Slug::make()` normalizza in ASCII (translitterazione
        // + minuscole) usando `_` come separatore, pensato per chiavi/id. Per lo
        // slug pubblico URL-friendly convertiamo `_` in `-`; nessuna copia locale
        // della logica di slugificazione.
        $slug = str_replace('_', '-', TextSlug::make($text)) ?: $fallback;

        return self::limit($slug, self::BASE_LENGTH, $fallback);
    }

    /**
     * Base + unicità in un solo passaggio, per i reparti che partono da campi
     * grezzi invece che da una riga già formata.
     *
     * @param array<int, mixed> $parts
     */
    public static function fromParts(
        array $parts,
        string $modelClass = Immobile::class,
        int|string|null $excludeId = null,
        string $fallback = 'immobile'
    ): string {
        return self::unique(self::base($parts, $fallback), $modelClass, $excludeId, $fallback);
    }

    /**
     * Rende lo slug univoco nella tabella di `$modelClass`, escludendo
     * l'eventuale riga corrente ($excludeId) così che un re-sync/update non lo
     * faccia crescere. Aggiunge `-2`, `-3`, … finché trova un valore libero.
     */
    public static function unique(
        string $base,
        string $modelClass = Immobile::class,
        int|string|null $excludeId = null,
        string $fallback = 'immobile'
    ): string {
        $base = self::limit($base !== '' ? $base : $fallback, self::MAX_LENGTH, $fallback);
        $slug = $base;
        $n = 1;

        while (self::taken($slug, $modelClass, $excludeId)) {
            $n++;
            $suffix = '-'.$n;
            $slug = self::limit($base, self::MAX_LENGTH - strlen($suffix), $fallback).$suffix;
        }

        return $slug;
    }

    private static function limit(string $slug, int $length, string $fallback = 'immobile'): string
    {
        if (strlen($slug) <= $length) {
            return $slug;
        }

        $slug = rtrim(substr($slug, 0, $length), '-_');

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * `$modelClass` deve essere un Model del modulo con colonna `slug`. Le
     * eccezioni (DB non ancora migrato) valgono "slug libero", come prima.
     *
     * @param class-string $modelClass
     */
    private static function taken(string $slug, string $modelClass, int|string|null $excludeId): bool
    {
        try {
            $row = $modelClass::find(['slug' => $slug], 1);
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($row) || !isset($row['id'])) {
            return false;
        }

        return $excludeId === null || (int) $row['id'] !== (int) $excludeId;
    }
```

Nota: `unique()` guadagna un quarto parametro `$fallback`; il test dello Step 1 verifica i primi tre, che sono quelli che i chiamanti passano.

`fromRow()` resta invariata: chiama `self::unique($base, Immobile::class, $excludeId)` implicitamente tramite i default.

- [ ] **Step 4: Rimuovere il duplicato da ResidenzaForm**

In `src/Support/Forms/ResidenzaForm.php` cancellare i metodi `uniqueSlug()` e `slugTaken()` e l'import `use Wonder\Plugin\Immobili\Models\Residenza;` se non più usato altrove nel file (verificare con `grep -n 'Residenza' src/Support/Forms/ResidenzaForm.php`).

- [ ] **Step 5: Aggiornare i tre chiamanti**

`src/Resources/ResidenzaResource.php` — sostituire la riga 409:

```php
            $values['slug'] = Slug::fromParts(
                [(string) ($values['nome'] ?? '')],
                Residenza::class,
                $excludeId,
                'residenza'
            );
```

Aggiungere in testa al file `use Wonder\Plugin\Immobili\Support\Slug;` se assente.

`src/Seeding/ResidenzaSeeder.php` — sostituire la riga 115:

```php
                'slug'              => Slug::fromParts([(string) $demo['nome']], Residenza::class, null, 'residenza'),
```

Aggiungere `use Wonder\Plugin\Immobili\Support\Slug;` se assente.

`http/api/task/reindex.php` — la riga 76 resta valida perché `Immobile` è il default:

```php
        $fields['slug'] = Slug::unique($base, Immobile::class, $id);
```

- [ ] **Step 6: Eseguire la suite**

```bash
find src http -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
bash tests/run.sh | tail -3
```

Expected: `SINTASSI OK` e `TUTTE LE SUITE PASSATE`. smoke passa da 98 a **105** asserzioni.

- [ ] **Step 7: Commit**

```bash
git add src/Support/Slug.php src/Support/Forms/ResidenzaForm.php src/Resources/ResidenzaResource.php src/Seeding/ResidenzaSeeder.php http/api/task/reindex.php tests/smoke.php
git commit -m "refactor(immobili): Slug parametrica sul modello, via il duplicato di ResidenzaForm"
```

---

### Task 4: `Media/MediaUrl` — un solo posto per URL e varianti

`ResidenzaPresenter::{imageUrl,previewUrl,firstFile}` e `ImmobilePresenter::{variants,cover}` ricalcolano entrambi la base di upload e il suffisso `-620.webp`.

**Files:**
- Create: `src/Media/MediaUrl.php`
- Modify: `src/Catalog/ResidenzaPresenter.php`
- Modify: `src/Catalog/ImmobilePresenter.php:703-722` (metodo `variants()`)
- Test: `tests/smoke.php`

**Interfaces:**
- Produces:
```php
MediaUrl::url(string $file, string $folder): string          // '' se $file vuoto; passthrough se $file è già URL assoluto
MediaUrl::preview(string $file, string $folder): string      // variante -620.webp; passthrough se URL assoluto
MediaUrl::variant(string $file, string $folder, int $width): string
MediaUrl::firstFile(mixed $stored): string                   // primo filename da JSON/array/stringa legacy
```

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, prima del blocco `echo "Route cartello vetrina venduto\n";`, aggiungere:

```php
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
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A3 '^MediaUrl'`
Expected: fatal error `Class "Wonder\Plugin\Immobili\Media\MediaUrl" not found`.

- [ ] **Step 3: Creare MediaUrl**

```php
<?php

namespace Wonder\Plugin\Immobili\Media;

use Wonder\App\Support\MediaFileManager;

/**
 * Composizione degli URL pubblici dei file caricati e delle loro varianti
 * responsive. Fonte unica per entrambi i reparti: la cartella arriva dal
 * `Model::$folder` del reparto chiamante ('immobili', 'residenze', …).
 *
 * I valori già in forma di URL assoluto (es. immagini di seed) passano
 * invariati e non hanno varianti responsive.
 */
final class MediaUrl
{
    /** Larghezza della variante usata come anteprima in card e liste. */
    public const PREVIEW_WIDTH = 620;

    /** URL pubblico di un filename dentro `$folder`. '' se il file è vuoto. */
    public static function url(string $file, string $folder): string
    {
        $file = trim($file);

        if ($file === '') {
            return '';
        }

        if (self::isAbsolute($file)) {
            return $file;
        }

        $path = $GLOBALS['PATH'] ?? null;
        $base = is_object($path) ? rtrim((string) ($path->upload ?? ''), '/') : '';

        return $base.'/'.trim($folder, '/').'/'.$file;
    }

    /** URL della variante di anteprima (-620.webp). '' se il file è vuoto. */
    public static function preview(string $file, string $folder): string
    {
        return self::variant($file, $folder, self::PREVIEW_WIDTH);
    }

    /** URL di una variante responsive webp a larghezza esplicita. */
    public static function variant(string $file, string $folder, int $width): string
    {
        $file = trim($file);

        if ($file === '' || self::isAbsolute($file)) {
            return self::url($file, $folder);
        }

        $dot = strrpos($file, '.');
        $stem = $dot === false ? $file : substr($file, 0, $dot);

        return self::url($stem.'-'.$width.'.webp', $folder);
    }

    /**
     * Primo filename di una colonna file/immagine: array JSON, array già
     * decodificato o formato legacy a stringa singola. '' se assente.
     */
    public static function firstFile(mixed $stored): string
    {
        $files = MediaFileManager::decodeStoredFiles($stored);

        return (string) ($files[0] ?? '');
    }

    private static function isAbsolute(string $file): bool
    {
        return filter_var($file, FILTER_VALIDATE_URL) !== false;
    }
}
```

- [ ] **Step 4: Eseguire e verificare che passi**

Run: `php tests/smoke.php 2>&1 | grep -A12 '^MediaUrl'`
Expected: 11 righe `✓`, nessuna `✗`.

- [ ] **Step 5: Portare ResidenzaPresenter su MediaUrl**

In `src/Catalog/ResidenzaPresenter.php` sostituire i corpi di `imageUrl()`, `previewUrl()` e `firstFile()` con la delega, mantenendo le firme pubbliche (sono usate da `residenze/detail.php`):

```php
    /** URL upload assoluto di un filename della cartella residenze. */
    public static function imageUrl(string $file): string
    {
        return MediaUrl::url($file, Residenza::$folder);
    }

    /** URL della variante webp responsive -620 di un filename; '' se vuoto. */
    public static function previewUrl(string $file): string
    {
        return MediaUrl::preview($file, Residenza::$folder);
    }

    /**
     * Primo filename di una colonna file/immagine (JSON array, array già
     * decodificato o formato legacy stringa). '' se assente.
     */
    public static function firstFile(mixed $stored): string
    {
        return MediaUrl::firstFile($stored);
    }
```

Aggiungere gli import `use Wonder\Plugin\Immobili\Media\MediaUrl;` e `use Wonder\Plugin\Immobili\Models\Residenza;`, rimuovere `use Wonder\App\Support\MediaFileManager;` se non più usato (lo è ancora in `files()` — verificare con `grep -n MediaFileManager src/Catalog/ResidenzaPresenter.php`), e cancellare la costante `private const FOLDER = 'residenze';`.

- [ ] **Step 6: Portare ImmobilePresenter::variants() su MediaUrl**

In `src/Catalog/ImmobilePresenter.php`, dentro `variants()` (righe ~703-722), sostituire la costruzione a mano di `'thumb' => $base.'-620.webp'` con `MediaUrl::variant(...)`, mantenendo identiche le chiavi dell'array restituito. Aggiungere `use Wonder\Plugin\Immobili\Media\MediaUrl;`.

Leggere il metodo prima di modificarlo: se `$base` è già uno stem senza estensione, usare `MediaUrl::url($base.'-620.webp', Immobile::$folder)` invece di `variant()`, che si aspetta un filename con estensione.

- [ ] **Step 7: Eseguire la suite**

```bash
find src -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
bash tests/run.sh | tail -3
```

Expected: `SINTASSI OK`, `TUTTE LE SUITE PASSATE`, smoke a **116** asserzioni. La suite `resource-form` verifica già gli URL delle immagini manuali e del feed: deve restare a 36 asserzioni verdi.

- [ ] **Step 8: Commit**

```bash
git add src/Media/MediaUrl.php src/Catalog/ResidenzaPresenter.php src/Catalog/ImmobilePresenter.php tests/smoke.php
git commit -m "refactor(immobili): MediaUrl come fonte unica di URL upload e varianti"
```

---

### Task 5: `Support/Forms/FormText` — la base condivisa dei due form

`ImmobileForm::text()` e `ResidenzaForm::text()` sono lo stesso metodo con un prefisso diverso; `ResidenzaForm::energyClasses()` è un proxy verso `ImmobileForm`.

**Deviazione consapevole dalla spec**: la spec prevedeva in `FormText` anche "le tassonomie comuni". `municipalities()` e `comuneNome()` **restano** deleghe a `ImmobileForm`, perché spostarle trascinerebbe con sé il privato `taxonomy()`, usato da altri sei metodi del form immobili, dentro una classe condivisa che non ha motivo di ospitarlo. I comuni *sono* la tassonomia canonica degli immobili e la residenza la riusa: la delega esprime esattamente questo. Si sposta invece `energyClasses()`, che dipende solo dal privato `filteredOption()`.

**Files:**
- Create: `src/Support/Forms/FormText.php`
- Modify: `src/Support/Forms/ImmobileForm.php`
- Modify: `src/Support/Forms/ResidenzaForm.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Produces:
```php
FormText::resolve(string $section, string $key, ?string $fallback = null): string  // 'forms.<section>.<key>'
FormText::energyClasses(): array                                                   // ['' => '--', 'A+' => [...], …]
```
- Invariate e ancora pubbliche: `ImmobileForm::text()`, `ImmobileForm::energyClasses()`, `ResidenzaForm::text()`, `ResidenzaForm::energyClasses()`, `ResidenzaForm::municipalities()`, `ResidenzaForm::comuneNome()`.

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, prima del blocco `echo "MediaUrl\n";`, aggiungere:

```php
echo "FormText\n";
$formText = \Wonder\Plugin\Immobili\Support\Forms\FormText::class;

// Senza __t() registrato il fallback è la chiave stessa: è il comportamento
// difensivo che serve quando le lang del modulo non sono ancora caricate.
$assert(
    $formText::resolve('immobili', 'fields.nome') === 'forms.immobili.fields.nome'
    || is_string($formText::resolve('immobili', 'fields.nome')),
    "resolve compone forms.<section>.<key>"
);
$assert($formText::resolve('residenze', 'x', 'ripiego') !== '', "il fallback esplicito non è mai vuoto");

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
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A2 '^FormText'`
Expected: fatal error `Class "Wonder\Plugin\Immobili\Support\Forms\FormText" not found`.

- [ ] **Step 3: Creare FormText**

```php
<?php

namespace Wonder\Plugin\Immobili\Support\Forms;

use Throwable;

/**
 * Base condivisa dei form di reparto. Ospita ciò che immobili e residenze
 * usano allo stesso modo: la risoluzione dei testi (`forms.<reparto>.<key>`)
 * e le classi energetiche, che seguono la legge italiana e non dipendono dal
 * reparto.
 *
 * Le tassonomie (comuni, tipologie, …) restano su `ImmobileForm`: sono la
 * tassonomia canonica degli immobili, che le residenze riusano.
 */
abstract class FormText
{
    /**
     * Testo tradotto di un form di reparto. Difensivo: `pageSchema()` e
     * `labelSchema()` vengono letti anche prima che le traduzioni del modulo
     * siano disponibili, e in quel caso si restituisce il fallback (o la
     * chiave) invece di sollevare.
     */
    public static function resolve(string $section, string $key, ?string $fallback = null): string
    {
        $translationKey = 'forms.'.$section.'.'.$key;

        if (function_exists('__t')) {
            try {
                return (string) __t($translationKey);
            } catch (Throwable) {
                // Traduzioni non ancora caricate: si ripiega sotto.
            }
        }

        return $fallback ?? $translationKey;
    }

    /**
     * Classi energetiche selezionabili, marcate con la legge di riferimento
     * (`data-legge`) così che il form possa filtrarle in cascata.
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function energyClasses(): array
    {
        $options = ['' => '--'];

        foreach (['A+', 'A'] as $class) {
            $options[$class] = self::filteredOption($class, 'legge', ['0']);
        }

        foreach (['A4', 'A3', 'A2', 'A1'] as $class) {
            $options[$class] = self::filteredOption($class, 'legge', ['1']);
        }

        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $class) {
            $options[$class] = self::filteredOption($class, 'legge', ['0', '1']);
        }

        return $options;
    }

    /**
     * Opzione con i metadati di filtro usati dalla cascata JS del form.
     *
     * @param array<int, string> $values
     * @return array<string, mixed>
     */
    protected static function filteredOption(string $label, string $filter, array $values): array
    {
        return [
            'name' => $label,
            'filter' => [
                $filter => (string) json_encode(array_values($values), JSON_UNESCAPED_UNICODE),
            ],
        ];
    }
}
```

Il corpo di `filteredOption()` è identico all'originale in `ImmobileForm`: cambia solo la visibilità, da `private` a `protected`. La forma dell'array (`name` + `filter`) è consumata dalla cascata JS del form backend e non va toccata.

- [ ] **Step 4: Far delegare i due form**

In `src/Support/Forms/ImmobileForm.php`:
- cambiare la dichiarazione in `final class ImmobileForm extends FormText`;
- sostituire il corpo di `text()` con `return self::resolve('immobili', $key, $fallback);`;
- sostituire il corpo di `energyClasses()` con `return FormText::energyClasses();`;
- rimuovere il metodo privato `filteredOption()` (ora ereditato `protected`) e l'import `use Throwable;` se non più usato.

In `src/Support/Forms/ResidenzaForm.php`:
- cambiare la dichiarazione in `final class ResidenzaForm extends FormText`;
- sostituire il corpo di `text()` con `return self::resolve('residenze', $key, $fallback);`;
- sostituire il corpo di `energyClasses()` con `return FormText::energyClasses();`;
- lasciare `municipalities()` e `comuneNome()` come deleghe a `ImmobileForm`, aggiungendo il commento:

```php
    /**
     * I comuni sono la tassonomia canonica degli immobili: la residenza la
     * riusa invece di mantenerne una propria.
     *
     * @return array<string, string|array<string, mixed>>
     */
```

- [ ] **Step 5: Eseguire la suite**

```bash
find src -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
bash tests/run.sh | tail -3
```

Expected: `SINTASSI OK`, `TUTTE LE SUITE PASSATE`, smoke a **121** asserzioni. `resource-form` verifica il form backend completo: deve restare a 36 verdi.

- [ ] **Step 6: Commit**

```bash
git add src/Support/Forms tests/smoke.php
git commit -m "refactor(immobili): FormText come base condivisa dei form di reparto"
```

---

### Task 6: Dizionari del presenter → traduzioni

`ImmobilePresenter` dichiara otto costanti con etichette italiane hardcoded che duplicano `ImmobileForm::OPTION_KEYS`. Effetto: il dettaglio immobile è monolingua italiano anche sul sito inglese.

**Le traduzioni IT esistenti coincidono già parola per parola con i dizionari hardcoded, tranne un caso**: `construction_type` non ha la chiave `other`, e `OPTION_KEYS['construction_type']['255']` punta a `standard` ("Civile") mentre il presenter mostra "Altro". È un bug preesistente di `ImmobileForm` che va corretto qui, altrimenti la deduplicazione cambierebbe l'etichetta del codice 255 da "Altro" a "Civile".

**Files:**
- Modify: `lang/it/forms.json` (aggiunge `immobili.options.construction_type.other`)
- Modify: `lang/en/forms.json` (idem)
- Modify: `src/Support/Forms/ImmobileForm.php` (`OPTION_KEYS['construction_type']['255']` → `'other'`)
- Modify: `src/Catalog/ImmobilePresenter.php` (rimuove 8 costanti, usa `ImmobileForm::options()`)
- Test: `tests/smoke.php`

**Interfaces:**
- Consumes: `ImmobileForm::options(string $group, bool $withBlank = true): array<string, string>` (esistente).
- Produces: nessuna nuova API. `ImmobilePresenter::detailFields()` mantiene chiavi e forma identiche.

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, dopo il blocco `FormText`, aggiungere:

```php
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
```

`OPTION_KEYS` è `private`, ma `ReflectionClass::getConstant()` la legge senza bisogno di cambiarne la visibilità (verificato su PHP 8.2).

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A5 'Dizionari del presenter'`
Expected: `✗ nessun dizionario di dominio resta hardcoded nel presenter: KITCHEN, GARAGE, …` e `✗ lang/it: construction_type.other è tradotta`.

- [ ] **Step 3: Aggiungere la traduzione mancante**

In `lang/it/forms.json`, dentro `immobili.options.construction_type`, dopo `"luxury": "Lusso"`:

```json
                    "other": "Altro"
```

In `lang/en/forms.json`, stessa posizione:

```json
                    "other": "Other"
```

- [ ] **Step 4: Correggere la mappa in ImmobileForm**

In `src/Support/Forms/ImmobileForm.php`, dentro `OPTION_KEYS['construction_type']`:

```php
            '255' => 'other',
```

(era `'255' => 'standard'`, che duplicava il codice 2.)

- [ ] **Step 5: Rimuovere le costanti dal presenter**

In `src/Catalog/ImmobilePresenter.php`:

1. cancellare le costanti `KITCHEN`, `GARAGE`, `FURNISHING`, `WINDOW_FRAMES`, `TV_SYSTEM`, `CONSTRUCTION_TYPE`, `MAINTENANCE_STATE` (righe ~23-85);
2. aggiungere `use Wonder\Plugin\Immobili\Support\Forms\ImmobileForm;`;
3. sostituire ogni occorrenza nei tre rami di `detailFields()`:

| prima | dopo |
|---|---|
| `self::KITCHEN` | `ImmobileForm::options('kitchen', false)` |
| `self::GARAGE` | `ImmobileForm::options('garage', false)` |
| `self::FURNISHING` | `ImmobileForm::options('furnishing', false)` |
| `self::WINDOW_FRAMES` | `ImmobileForm::options('window_frames', false)` |
| `self::TV_SYSTEM` | `ImmobileForm::options('tv_system', false)` |
| `self::CONSTRUCTION_TYPE` | `ImmobileForm::options('construction_type', false)` |
| `self::MAINTENANCE_STATE` | `ImmobileForm::options('construction_status', false)` |

Attenzione a `MAINTENANCE_STATE` → gruppo **`construction_status`** (i nomi non coincidono).

`$withBlank = false` è obbligatorio: `enumValue()` cerca il valore per chiave e l'opzione `'' => '--'` non deve entrare nella mappa.

Comando per trovarle tutte:

```bash
grep -n 'self::\(KITCHEN\|GARAGE\|FURNISHING\|WINDOW_FRAMES\|TV_SYSTEM\|CONSTRUCTION_TYPE\|MAINTENANCE_STATE\)' src/Catalog/ImmobilePresenter.php
```

Expected dopo la modifica: nessun risultato.

- [ ] **Step 6: Eseguire la suite**

```bash
php -l src/Catalog/ImmobilePresenter.php
php -r 'json_decode(file_get_contents("lang/it/forms.json"), true) ?: exit("JSON it NON VALIDO\n"); json_decode(file_get_contents("lang/en/forms.json"), true) ?: exit("JSON en NON VALIDO\n"); echo "JSON OK\n";'
bash tests/run.sh | tail -3
```

Expected: `JSON OK`, `TUTTE LE SUITE PASSATE`, smoke a **125** asserzioni.

- [ ] **Step 7: Commit**

```bash
git add src/Catalog/ImmobilePresenter.php src/Support/Forms/ImmobileForm.php lang/it/forms.json lang/en/forms.json tests/smoke.php
git commit -m "fix(immobili): etichette del dettaglio dalle traduzioni, non più hardcoded in italiano

Corregge anche construction_type 255, che puntava a 'standard' (Civile)
invece che a 'other' (Altro)."
```

---

### Task 7: Gate di autenticazione unico per i task API

`http/api/task/{seed,residenze-seed,reindex}.php` ripetono ~25 righe identiche: rilevamento ambiente locale, estrazione del Bearer, risposta 403.

**Files:**
- Create: `http/api/task/_guard.php`
- Modify: `http/api/task/seed.php`
- Modify: `http/api/task/residenze-seed.php`
- Modify: `http/api/task/reindex.php`

**Interfaces:**
- Produces: `immobiliTaskGuard(string $label): void` — in ambiente locale ritorna; altrimenti pretende il token di `@immobili` e, se manca o non è valido, emette 403 JSON ed esce. `$label` compare nel messaggio d'errore ("Seed", "Reindex").

- [ ] **Step 1: Creare il guard**

```php
<?php

use Wonder\Plugin\Immobili\Sync\SyncApiUser;

/**
 * Gate condiviso dei task amministrativi del modulo (seed, reindex).
 *
 * In ambiente locale passano senza credenziali, così da poterli lanciare dal
 * browser durante lo sviluppo. Fuori dal locale richiedono il token
 * dell'utente API dedicato `@immobili`, presentato come
 * `Authorization: Bearer <token>` oppure come `?token=<token>` (fallback per i
 * client che non possono impostare header).
 *
 * Da non confondere con `_bearer.php`, che non autorizza: si limita a
 * sintetizzare l'header Authorization da `?token=` per gli endpoint che
 * passano da `Wonder\Api\Endpoint`.
 */

if (!function_exists('immobiliTaskIsLocal')) {
    function immobiliTaskIsLocal(): bool
    {
        if (getenv('APP_ENV') === 'local') {
            return true;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        return (bool) preg_match(
            '/(^localhost|127\.0\.0\.1|\.test$|\.local$|\.localhost$|\.ddev\.site$)/',
            $host
        );
    }
}

if (!function_exists('immobiliTaskPresentedToken')) {
    function immobiliTaskPresentedToken(): string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (is_string($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $m)) {
            return $m[1];
        }

        return trim((string) ($_GET['token'] ?? ''));
    }
}

if (!function_exists('immobiliTaskGuard')) {
    /**
     * Interrompe la richiesta con 403 se non siamo in locale e il token
     * presentato non è valido. Imposta anche il Content-Type JSON della
     * risposta, comune a tutti i task.
     */
    function immobiliTaskGuard(string $label): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (immobiliTaskIsLocal()) {
            return;
        }

        if (SyncApiUser::authorize(immobiliTaskPresentedToken())) {
            return;
        }

        http_response_code(403);
        echo json_encode([
            'success'  => false,
            'status'   => 403,
            'response' => ['message' => $label.' disponibile solo in ambiente locale o con token API valido.'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
```

- [ ] **Step 2: Sostituire il blocco duplicato nei tre handler**

In `http/api/task/seed.php`, `residenze-seed.php` e `reindex.php`, rimuovere tutto il blocco che va da `header('Content-Type: application/json; charset=utf-8');` fino alla chiusura dell'`if (!$isLocal && !SyncApiUser::authorize(...)) { … exit; }` incluso, e sostituirlo con:

```php
require __DIR__.'/_guard.php';

immobiliTaskGuard('Seed');       // 'Seed' in seed.php e residenze-seed.php, 'Reindex' in reindex.php
```

Rimuovere anche `use Wonder\Plugin\Immobili\Sync\SyncApiUser;` dai tre file: ora è il guard a usarlo.

- [ ] **Step 3: Verificare che i tre handler siano dimagriti**

```bash
wc -l http/api/task/seed.php http/api/task/residenze-seed.php http/api/task/reindex.php
grep -c 'isLocal\|getallheaders\|REDIRECT_HTTP_AUTHORIZATION' http/api/task/seed.php http/api/task/residenze-seed.php http/api/task/reindex.php
```

Expected: `seed.php` ~28 righe (era 54), `residenze-seed.php` ~26 (era 52), `reindex.php` ~62 (era 88); tutti e tre con conteggio `0` sul secondo comando.

- [ ] **Step 4: Verificare sintassi e comportamento del guard**

```bash
find http -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"

# Il guard in ambiente locale non deve interrompere.
APP_ENV=local php -r '
require "vendor/autoload.php";
require "http/api/task/_guard.php";
immobiliTaskGuard("Seed");
echo "PASSA IN LOCALE\n";
' 2>&1 | tail -1
```

Expected: `SINTASSI OK` e `PASSA IN LOCALE`.

- [ ] **Step 5: Eseguire la suite**

Run: `bash tests/run.sh | tail -3`
Expected: `TUTTE LE SUITE PASSATE`

- [ ] **Step 6: Commit**

```bash
git add http/api/task
git commit -m "refactor(immobili): gate unico per i task API, via il blocco auth triplicato"
```

---

### Task 8: `Sync/ReindexService` — logica di dominio fuori dall'handler

`reindex.php` contiene il backfill degli slug e dei campi di ricerca dentro un handler HTTP.

**Files:**
- Create: `src/Sync/ReindexService.php`
- Modify: `http/api/task/reindex.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Produces: `ReindexService::run(): array{updated: int}` — ricalcola `comune_nome` / `tipologia_nome` / `ricerca` su tutti gli immobili non cancellati e fa il backfill dello slug per quelli che ne sono privi. Idempotente.

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, dopo il blocco dei dizionari, aggiungere:

```php
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
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A5 '^ReindexService'`
Expected: `✗ ReindexService esiste`.

- [ ] **Step 3: Creare il service**

```php
<?php

namespace Wonder\Plugin\Immobili\Sync;

use Wonder\Plugin\Immobili\Catalog\ImmobilePresenter;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Backfill idempotente dei campi derivati usati dalla ricerca SQL della lista
 * (`comune_nome`, `tipologia_nome`, `ricerca`) e dello slug pubblico sui
 * record che ne sono privi.
 *
 * Serve per i record importati prima dell'introduzione delle colonne: dopo il
 * sync questi valori sono già mantenuti aggiornati. Sicuro da rieseguire.
 */
final class ReindexService
{
    /**
     * @return array{updated: int}
     */
    public function run(): array
    {
        $presenter = new ImmobilePresenter();

        $rows = Immobile::find(['deleted' => 'false']);
        $rows = is_array($rows) ? (isset($rows['id']) ? [$rows] : array_values($rows)) : [];

        $updated = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $fields = $presenter->searchFields($row);

            // Backfill dello slug per i record che ne sono privi (es. dopo la
            // migrazione dir→slug): base leggibile resa univoca, escludendo il
            // record stesso.
            if (trim((string) ($row['slug'] ?? '')) === '') {
                $base = Slug::base([
                    $fields['tipologia_nome'] ?? '',
                    $row['strada'] ?? '',
                    $row['indirizzo'] ?? '',
                    $fields['comune_nome'] ?? '',
                ]);
                $fields['slug'] = Slug::unique($base, Immobile::class, $id);
            }

            Immobile::update($fields, $id);
            $updated++;
        }

        return ['updated' => $updated];
    }
}
```

- [ ] **Step 4: Ridurre l'handler**

`http/api/task/reindex.php` diventa, per intero:

```php
<?php

use Wonder\Plugin\Immobili\Sync\ReindexService;

/**
 * Backfill idempotente dei campi derivati per la ricerca SQL della lista.
 *
 *   GET /api/immobili/reindex/   → ricalcola comune_nome/tipologia_nome e fa il
 *                                  backfill dello slug per tutti gli immobili non
 *                                  cancellati che ne sono privi
 *
 * Disponibile senza credenziali solo in ambiente locale; fuori dal locale
 * richiede il token dell'utente API `@immobili`. Vedi `_guard.php`.
 * La logica vive in `Wonder\Plugin\Immobili\Sync\ReindexService`.
 */

require __DIR__.'/_guard.php';

immobiliTaskGuard('Reindex');

echo json_encode([
    'success'  => true,
    'status'   => 200,
    'response' => (new ReindexService())->run(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
```

- [ ] **Step 5: Eseguire la suite**

```bash
php -l src/Sync/ReindexService.php && php -l http/api/task/reindex.php
bash tests/run.sh | tail -3
```

Expected: nessun errore di sintassi, `TUTTE LE SUITE PASSATE`, smoke a **130** asserzioni.

- [ ] **Step 6: Commit**

```bash
git add src/Sync/ReindexService.php http/api/task/reindex.php tests/smoke.php
git commit -m "refactor(immobili): ReindexService, l'handler HTTP torna a fare solo HTTP"
```

---

### Task 9: `CardViewModel` e la card unificata

I due gusci HTML sono già identici; divergono solo per il tipo di input e per le righe del corpo.

**Vincolo scoperto in analisi**: `ImmobileQuery::cards()` e `ImmobilePresenter::cards()` **non vanno toccati**. I loro oggetti sono consumati anche da `http/api/frontend/search.php`, che li serializza in JSON per il JS della lista: cambiarne la forma romperebbe un contratto esterno. Il view-model è quindi una classe **aggiuntiva**, costruita dalle pagine subito prima di rendere le card.

**Fix di comportamento incluso**: l'attuale `card.php` stampa `$immobile->superficie`, cioè il valore grezzo della colonna ("120"), invece di `prettySuperficie` ("120 mq"). Il view-model usa `prettySuperficie`.

**Files:**
- Create: `src/Catalog/CardViewModel.php`
- Modify: `view/components/card.php`
- Modify: `view/pages/frontend/list.php`, `sold.php`, `residenze/list.php`, `residenze/detail.php`
- Delete: `view/components/residenze/card.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Consumes: `ImmobileQuery::cards(array $rows): array<int, object>` (invariata), `ResidenzaPresenter::cover(array $row): string`, `ResidenzaPresenter::stato(array $row): string`, `ResidenzaPresenter::timelineLabel(?int $anno, ?int $mese): string`.
- Produces:
```php
final class CardViewModel {
    public readonly string $url;
    public readonly string $cover;
    public readonly ?object $badge;     // (object) ['label' => string, 'variant' => string]
    public readonly string $eyebrow;
    public readonly string $title;
    public readonly string $subtitle;
    public readonly string $highlight;
    public readonly string $excerpt;
    public readonly array  $meta;       // array<int, object{icon: string, text: string}>

    public static function fromImmobile(object $immobile): self;
    public static function fromImmobili(array $items): array;          // array<int, self>
    public static function fromResidenza(array $row, ?ResidenzaPresenter $presenter = null): self;
    public static function fromResidenze(array $rows, ?ResidenzaPresenter $presenter = null): array;
}
```

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, dopo il blocco `ReindexService`, aggiungere:

```php
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

$cardSource = (string) file_get_contents(dirname(__DIR__).'/view/components/card.php');
$assert(
    !str_contains($cardSource, 'residenza') && !str_contains($cardSource, 'Residenza'),
    'card.php non conosce i reparti: nessun ramo per tipo'
);
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A3 'CardViewModel'`
Expected: fatal error `Class "Wonder\Plugin\Immobili\Catalog\CardViewModel" not found`.

- [ ] **Step 3: Creare il view-model**

```php
<?php

namespace Wonder\Plugin\Immobili\Catalog;

/**
 * Forma comune delle card di lista dei due reparti.
 *
 * Esiste perché immobili e residenze hanno lo stesso guscio visivo ma partono
 * da dati diversi: qui le differenze si appiattiscono in slot opzionali, così
 * `view/components/card.php` non deve sapere cosa sta rendendo. I campi non
 * pertinenti a un reparto sono stringa vuota, mai assenti.
 *
 * NON sostituisce `ImmobilePresenter::card()`: quegli oggetti restano com'erano
 * perché li serializza anche l'API JSON della lista (`http/api/frontend/search.php`).
 */
final class CardViewModel
{
    /**
     * @param object|null                       $badge (object) ['label' => string, 'variant' => string]
     * @param array<int, object>                $meta  (object) ['icon' => string, 'text' => string]
     */
    private function __construct(
        public readonly string $url,
        public readonly string $cover,
        public readonly ?object $badge,
        public readonly string $eyebrow,
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $highlight,
        public readonly string $excerpt,
        public readonly array $meta,
    ) {
    }

    /** @param object $immobile oggetto prodotto da ImmobilePresenter::card() */
    public static function fromImmobile(object $immobile): self
    {
        $badge = null;

        if (!empty($immobile->sold)) {
            $badge = (object) [
                'label'   => (string) __t('components.immobili.card.sold'),
                'variant' => 'text-bg-danger',
            ];
        } elseif (!empty($immobile->evidence)) {
            $badge = (object) [
                'label'   => (string) __t('components.immobili.card.featured'),
                'variant' => 'text-bg-dark',
            ];
        }

        $tipologia = trim((string) ($immobile->tipologia ?? ''));
        $contratto = trim((string) ($immobile->contratto ?? ''));
        $eyebrow = $tipologia !== ''
            ? trim($tipologia.($contratto !== '' ? ' · '.$contratto : ''))
            : '';

        $meta = [];

        if (trim((string) ($immobile->prettySuperficie ?? '')) !== '') {
            $meta[] = (object) ['icon' => 'bi bi-rulers', 'text' => (string) $immobile->prettySuperficie];
        }

        if ((int) ($immobile->locali ?? 0) > 0) {
            $meta[] = (object) [
                'icon' => 'bi bi-door-open',
                'text' => (int) $immobile->locali.' '.__t('components.immobili.card.rooms'),
            ];
        }

        if ((int) ($immobile->camere ?? 0) > 0) {
            $meta[] = (object) ['icon' => 'bi bi-house', 'text' => (string) (int) $immobile->camere];
        }

        if ((int) ($immobile->bagni ?? 0) > 0) {
            $meta[] = (object) ['icon' => 'bi bi-droplet', 'text' => (string) (int) $immobile->bagni];
        }

        return new self(
            url:       (string) ($immobile->url ?? '#'),
            cover:     (string) ($immobile->cover ?? ''),
            badge:     $badge,
            eyebrow:   $eyebrow,
            title:     (string) ($immobile->prettyName ?? ''),
            subtitle:  (string) ($immobile->prettyAddress ?? ''),
            highlight: trim((string) ($immobile->prezzo ?? '')) !== ''
                            ? (string) ($immobile->prettyPrezzo ?? '')
                            : '',
            excerpt:   '',
            meta:      $meta,
        );
    }

    /**
     * @param array<int, object> $items
     * @return array<int, self>
     */
    public static function fromImmobili(array $items): array
    {
        return array_map(
            static fn (object $item): self => self::fromImmobile($item),
            array_values(array_filter($items, 'is_object'))
        );
    }

    /** @param array<string, mixed> $row riga residenza */
    public static function fromResidenza(array $row, ?ResidenzaPresenter $presenter = null): self
    {
        $presenter ??= new ResidenzaPresenter();

        $stato = ResidenzaPresenter::stato($row);

        $timeline = trim(
            ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0))
            .' → '.
            ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0)),
            ' →'
        );

        $meta = [];

        if ($timeline !== '') {
            $meta[] = (object) ['icon' => 'bi bi-calendar3', 'text' => $timeline];
        }

        return new self(
            url:       (string) ($row['url'] ?? '#'),
            cover:     $presenter->cover($row),
            badge:     (object) [
                'label'   => (string) __t('pages.residenze.stato.'.$stato),
                'variant' => 'text-bg-primary',
            ],
            eyebrow:   '',
            title:     (string) ($row['nome'] ?? ''),
            subtitle:  (string) ($row['comune_nome'] ?? ''),
            highlight: '',
            excerpt:   (string) ($row['descrizione_breve'] ?? ''),
            meta:      $meta,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, self>
     */
    public static function fromResidenze(array $rows, ?ResidenzaPresenter $presenter = null): array
    {
        $presenter ??= new ResidenzaPresenter();

        return array_map(
            static fn (array $row): self => self::fromResidenza($row, $presenter),
            array_values(array_filter($rows, 'is_array'))
        );
    }
}
```

**Nota per il test**: `__t()` non è definita in `tests/smoke.php`. Aggiungere in cima al file, subito dopo il `require` dell'autoloader, lo stub già usato da `tests/residenze.php`:

```php
if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        return $key;
    }
}
```

Se `smoke.php` la definisce già, saltare questo passaggio.

- [ ] **Step 4: Riscrivere card.php**

`view/components/card.php` diventa, per intero:

```php
<?php

/**
 * Card di lista, comune ai due reparti. Riceve un CardViewModel già pronto:
 * qui non si sa (né si deve sapere) se si sta rendendo un immobile o una
 * residenza. Solo classi utility wonder-image/lib.
 *
 * @var array $args ['item' => \Wonder\Plugin\Immobili\Catalog\CardViewModel]
 */

use Wonder\Plugin\Immobili\Catalog\CardViewModel;

$item = $args['item'] ?? null;

if (!$item instanceof CardViewModel) {
    return;
}

?>
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="<?= e($item->url) ?>">
    <div class="f-3-2 p-r bg-cover o-hidden" style="background-image:url('<?= e($item->cover) ?>')">
        <?php if ($item->badge !== null) { ?>
            <span class="p-a badge <?= e($item->badge->variant) ?>" style="top:.6rem;left:.6rem"><?= e($item->badge->label) ?></span>
        <?php } ?>
    </div>
    <div class="p-4 d-grid gap-2">
        <?php if ($item->eyebrow !== '') { ?>
            <div class="text-small tx-upper tx-muted"><?= e($item->eyebrow) ?></div>
        <?php } ?>
        <div class="text fw-600"><?= e($item->title) ?></div>
        <?php if ($item->subtitle !== '') { ?>
            <div class="text-small tx-muted"><i class="bi bi-geo-alt"></i> <?= e($item->subtitle) ?></div>
        <?php } ?>
        <?php if ($item->highlight !== '') { ?>
            <div class="text fw-700 tx-primary"><?= e($item->highlight) ?></div>
        <?php } ?>
        <?php if ($item->excerpt !== '') { ?>
            <div class="text-small mt-1"><?= e($item->excerpt) ?></div>
        <?php } ?>
        <?php if ($item->meta !== []) { ?>
            <div class="d-flex gap-4 text-small tx-muted mt-1">
                <?php foreach ($item->meta as $meta) { ?>
                    <span><i class="<?= e($meta->icon) ?>"></i> <?= e($meta->text) ?></span>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</a>
```

- [ ] **Step 5: Eliminare la card delle residenze**

```bash
git rm view/components/residenze/card.php
```

- [ ] **Step 6: Aggiornare i chiamanti**

`view/pages/frontend/residenze/list.php` — sostituire il blocco `foreach` con:

```php
                <?php foreach (CardViewModel::fromResidenze($rows, $presenter) as $item) {
                    Immobili::component('card', ['item' => $item]);
                } ?>
```

e aggiungere `use Wonder\Plugin\Immobili\Catalog\CardViewModel;` in testa.

`view/components/cards-grid.php` e `cards-swiper.php` — verranno riscritti nel Task 10. Per ora aggiornare solo la chiamata alla card:
- in `cards-grid.php`: `Immobili::component('card', ['item' => $immobile]);` dove `$immobili` è già stato convertito dal chiamante;
- in `cards-swiper.php`: `['args' => ['item' => $immobile]]`.

`view/pages/frontend/list.php` e `sold.php` — convertire prima di passare:

```php
$items = CardViewModel::fromImmobili($query->cards($rows));
```

più `use Wonder\Plugin\Immobili\Catalog\CardViewModel;`.

`view/pages/frontend/residenze/detail.php` riga 51:

```php
$linkedItems = CardViewModel::fromImmobili((new ImmobileQuery())->cards($linkedRows));
```

**Attenzione**: `list.php` usa `$items === []` per il ramo "nessun risultato" — continua a funzionare, `fromImmobili([])` restituisce `[]`.

- [ ] **Step 7: Aggiornare il filtro dei collection component**

`cards-grid.php` e `cards-swiper.php` filtrano con `array_filter($immobili, 'is_object')`: i `CardViewModel` sono oggetti, quindi il filtro resta valido. Verificarlo esplicitamente prima di procedere al Task 10.

- [ ] **Step 8: Eseguire la suite**

```bash
find src view -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
bash tests/run.sh | tail -3
```

Expected: `SINTASSI OK`, `TUTTE LE SUITE PASSATE`, smoke a **~152** asserzioni.

- [ ] **Step 9: Commit**

```bash
git add -A src/Catalog/CardViewModel.php view tests/smoke.php
git commit -m "refactor(immobili): card unica per i due reparti su CardViewModel

La card mostra la superficie formattata (prettySuperficie) invece del
valore grezzo della colonna."
```

---

### Task 10: `cards.php` — una sola collezione, due layout

`cards-grid` e `cards-swiper` condividono tutto tranne il wrapper; `residenze/list.php` ha una terza copia della griglia scritta a mano.

**Files:**
- Create: `view/components/cards.php`
- Delete: `view/components/cards-grid.php`, `view/components/cards-swiper.php`
- Modify: `view/pages/frontend/list.php`, `sold.php`, `residenze/list.php`, `residenze/detail.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Produces: componente `cards` con args
  `['items' => CardViewModel[], 'layout' => 'grid'|'swiper', 'class' => string|string[], 'id' => string, 'slide_class' => string|string[], 'aria_label' => string]`.
  `layout` default `'grid'`; `id`, `slide_class` e `aria_label` sono ignorati in modalità griglia.

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, dopo il blocco `CardViewModel`, aggiungere:

```php
echo "Componente cards unificato\n";
$viewDir = dirname(__DIR__).'/view/components';
$assert(is_file($viewDir.'/cards.php'), 'cards.php esiste');
$assert(!is_file($viewDir.'/cards-grid.php'), 'cards-grid.php è stato assorbito');
$assert(!is_file($viewDir.'/cards-swiper.php'), 'cards-swiper.php è stato assorbito');

$cardsSource = (string) file_get_contents($viewDir.'/cards.php');
$assert(str_contains($cardsSource, "'swiper'"), 'cards.php gestisce il layout swiper');
$assert(str_contains($cardsSource, 'd-grid'), 'cards.php gestisce il layout griglia');

$residenzeList = (string) file_get_contents(dirname(__DIR__).'/view/pages/frontend/residenze/list.php');
$assert(
    !str_contains($residenzeList, 'd-grid col-3'),
    'la lista residenze non ha più la griglia scritta a mano'
);
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A5 'Componente cards'`
Expected: `✗ cards.php esiste`.

- [ ] **Step 3: Creare cards.php**

```php
<?php

/**
 * Collezione di card, comune ai due reparti e ai due layout.
 *
 * @var array $args [
 *     'items'       => \Wonder\Plugin\Immobili\Catalog\CardViewModel[],
 *     'layout'      => 'grid'|'swiper',   default 'grid'
 *     'class'       => string|string[],
 *     'id'          => string,            solo swiper
 *     'slide_class' => string|string[],   solo swiper
 *     'aria_label'  => string,            solo swiper
 * ]
 */

use Wonder\App\Dependencies;
use Wonder\Plugin\Immobili\Catalog\CardViewModel;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\View\Component as ViewComponent;

$items = $args['items'] ?? [];
$items = is_array($items)
    ? array_values(array_filter($items, static fn ($i): bool => $i instanceof CardViewModel))
    : [];

if ($items === []) {
    return;
}

$layout = ($args['layout'] ?? 'grid') === 'swiper' ? 'swiper' : 'grid';

// Classi extra: accettate come stringa o array, normalizzate a lista di token.
$extraClasses = $args['class'] ?? [];
$extraClasses = is_array($extraClasses) ? $extraClasses : [$extraClasses];
$extra = [];

foreach ($extraClasses as $value) {
    if (!is_scalar($value)) {
        continue;
    }

    foreach (preg_split('/\s+/', trim((string) $value)) ?: [] as $class) {
        if ($class !== '') {
            $extra[] = $class;
        }
    }
}

if ($layout === 'grid') {
    $classes = array_values(array_unique(array_merge(
        ['w-100', 'd-grid', 'col-3', 'col-t-2', 'col-p-1', 'gap-5'],
        $extra
    )));
    ?>
    <div class="<?= e(implode(' ', $classes)) ?>">
        <?php foreach ($items as $item) {
            Immobili::component('card', ['item' => $item]);
        } ?>
    </div>
    <?php
    return;
}

$cardPath = Immobili::viewPath('components/card.php');
$slides = array_map(
    static fn (CardViewModel $item): ViewComponent => ViewComponent::make(
        $cardPath,
        ['args' => ['item' => $item]]
    ),
    $items
);

Dependencies::swiper();

$swiper = __swiper()
    ->slides($slides)
    ->slidesPerView(1.05)
    ->spaceBetween(16)
    ->breakpoints([
        769 => ['slidesPerView' => 2, 'spaceBetween' => 20],
        993 => ['slidesPerView' => 3, 'spaceBetween' => 20],
    ])
    ->autoHeight()
    ->keyboard()
    ->watchOverflow()
    ->navigation();

$id = trim((string) ($args['id'] ?? ''));

if ($id !== '') {
    $swiper->id($id);
}

if ($extra !== []) {
    $swiper->addClass(implode(' ', $extra));
}

$slideClasses = $args['slide_class'] ?? [];

if (
    (is_string($slideClasses) && trim($slideClasses) !== '')
    || (is_array($slideClasses) && $slideClasses !== [])
) {
    $swiper->slideClass($slideClasses);
}

$ariaLabel = trim((string) ($args['aria_label'] ?? ''));

if ($ariaLabel !== '') {
    $swiper->attr('aria-label', $ariaLabel);
}

echo $swiper->render('wonder');
```

- [ ] **Step 4: Eliminare i due componenti assorbiti**

```bash
git rm view/components/cards-grid.php view/components/cards-swiper.php
```

- [ ] **Step 5: Aggiornare i quattro chiamanti**

`view/pages/frontend/list.php`:

```php
            <?php Immobili::component('cards', [
                'items' => $items,
                'class' => 'mt-4',
            ]); ?>
```

`view/pages/frontend/sold.php`: identica sostituzione (`cards-grid` → `cards`, `immobili` → `items`).

`view/pages/frontend/residenze/detail.php`:

```php
        <?php Immobili::component('cards', ['items' => $linkedItems, 'class' => 'mt-4']); ?>
```

`view/pages/frontend/residenze/list.php` — sostituire tutto il blocco `<div class="d-grid …"> foreach </div>` con:

```php
            <?php Immobili::component('cards', [
                'items' => CardViewModel::fromResidenze($rows, $presenter),
                'class' => 'mt-4',
            ]); ?>
```

- [ ] **Step 6: Verificare che non resti nessun riferimento ai nomi vecchi**

```bash
grep -rn "cards-grid\|cards-swiper" src view http config docs/getting-started || echo "NESSUN RESIDUO"
```

Expected: `NESSUN RESIDUO`. Se compaiono occorrenze in `CHANGELOG.md`, lasciarle: sono storia.

- [ ] **Step 7: Eseguire la suite**

```bash
find view -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
bash tests/run.sh | tail -3
```

Expected: `SINTASSI OK`, `TUTTE LE SUITE PASSATE`, smoke a **~158** asserzioni.

- [ ] **Step 8: Commit**

```bash
git add -A view tests/smoke.php
git commit -m "refactor(immobili): componente cards unico (grid|swiper), via la terza griglia a mano"
```

---

### Task 11: Simmetria delle cartelle view

Ultimo passo strutturale: i due reparti prendono la stessa forma e i componenti trasversali salgono in radice con nomi che dicono cosa fanno.

**Files:**
- Move: `view/components/features.php` → `view/components/specs.php`
- Move: `view/components/residenze/features.php` → `view/components/amenities.php`
- Move: `view/components/filters.php` → `view/components/immobili/filters.php`
- Move: `view/pages/frontend/list.php` → `view/pages/frontend/immobili/list.php`
- Move: `view/pages/frontend/detail.php` → `view/pages/frontend/immobili/detail.php`
- Move: `view/pages/frontend/sold.php` → `view/pages/frontend/immobili/sold.php`
- Modify: `config/routes/route.frontend.php`
- Modify: `view/pages/frontend/immobili/detail.php`, `view/pages/frontend/residenze/detail.php`
- Test: `tests/smoke.php`

Restano dove sono: `view/components/map.php`, `view/components/energy-class/*`, `view/components/residenze/timeline.php`, `view/pages/backend/immobili/*`, `view/layout/frontend/immobili.main.php`.

**Interfaces:**
- Produces: i nomi definitivi dei componenti — `card`, `cards`, `specs`, `amenities`, `map`, `energy-class/*`, `immobili/filters`, `residenze/timeline`.
- `specs` mantiene l'arg `['immobile' => object]`; `amenities` mantiene `['features' => array<int, string>]`.

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, dopo il blocco `Componente cards unificato`, aggiungere:

```php
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

// Ogni handler dichiarato nelle route frontend deve esistere davvero.
$frontendRoutes = \Wonder\Http\Route::load([dirname(__DIR__).'/config/routes/route.frontend.php']);
$missingHandlers = [];

foreach ($frontendRoutes as $route) {
    $handler = (string) ($route['handler'] ?? '');
    if ($handler !== '' && !is_file($handler)) {
        $missingHandlers[] = ($route['name'] ?? '?').' → '.$handler;
    }
}

$assert($missingHandlers === [], 'tutte le route frontend puntano a file esistenti: '.implode('; ', $missingHandlers));
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A6 'Simmetria delle cartelle'`
Expected: `✗ esiste view/components/specs.php` e simili.

- [ ] **Step 3: Spostare i file**

```bash
mkdir -p view/pages/frontend/immobili view/components/immobili

git mv view/components/features.php            view/components/specs.php
git mv view/components/residenze/features.php  view/components/amenities.php
git mv view/components/filters.php             view/components/immobili/filters.php

git mv view/pages/frontend/list.php   view/pages/frontend/immobili/list.php
git mv view/pages/frontend/detail.php view/pages/frontend/immobili/detail.php
git mv view/pages/frontend/sold.php   view/pages/frontend/immobili/sold.php
```

- [ ] **Step 4: Aggiornare le route**

In `config/routes/route.frontend.php` sostituire i cinque path:

| prima | dopo |
|---|---|
| `pages/frontend/list.php` | `pages/frontend/immobili/list.php` |
| `pages/frontend/sold.php` | `pages/frontend/immobili/sold.php` |
| `pages/frontend/detail.php` (gruppo `immobili.`) | `pages/frontend/immobili/detail.php` |
| `pages/frontend/detail.php` (gruppo `immobile.`, route `view`) | `pages/frontend/immobili/detail.php` |

```bash
sed -i '' \
  -e "s#pages/frontend/list\.php#pages/frontend/immobili/list.php#g" \
  -e "s#pages/frontend/sold\.php#pages/frontend/immobili/sold.php#g" \
  -e "s#pages/frontend/detail\.php#pages/frontend/immobili/detail.php#g" \
  config/routes/route.frontend.php
```

Verificare che `pages/frontend/residenze/*` non sia stato toccato:

```bash
grep -n 'pages/frontend' config/routes/route.frontend.php
```

Expected: le righe residenze restano `pages/frontend/residenze/list.php` e `pages/frontend/residenze/detail.php`.

- [ ] **Step 5: Aggiornare i nomi dei componenti nei chiamanti**

```bash
grep -rn "component('features'\|component('filters'\|component('residenze/features'" view
```

Sostituire:
- in `view/pages/frontend/immobili/detail.php`: `Immobili::component('features', …)` → `Immobili::component('specs', …)`
- in `view/pages/frontend/immobili/list.php`: `Immobili::component('filters', …)` → `Immobili::component('immobili/filters', …)`
- in `view/pages/frontend/residenze/detail.php`: `Immobili::component('residenze/features', …)` → `Immobili::component('amenities', …)`

Aggiornare anche il docblock in testa a `view/components/specs.php` e `view/components/amenities.php` perché descrivano il nuovo nome e il nuovo ruolo:

```php
/**
 * Specifiche dell'immobile: coppie attributo → valore. Quali attributi mostrare
 * (e in che ordine) è configurato in backend (Settings → Scheda immobile), con
 * fallback ai default del catalogo. Etichette e valori arrivano da
 * `AttributeCatalog` (fonte condivisa con la scheda PDF).
 *
 * Distinto da `amenities`, che elenca dotazioni presenti/assenti con icona.
 *
 * @var array $args ['immobile' => object]
 */
```

```php
/**
 * Dotazioni presenti, rese come icona + etichetta. Trasversale: oggi la usano
 * le residenze, ed è il posto giusto anche per le dotazioni booleane
 * dell'immobile (piscina, camino, allarme, …) quando smetteranno di comparire
 * in `specs` come "Sì".
 *
 * Distinto da `specs`, che mostra coppie attributo → valore.
 *
 * @var array $args ['features' => array<int, string> id]
 */
```

- [ ] **Step 6: Portare la classe energetica della residenza sul componente condiviso**

`view/pages/frontend/residenze/detail.php` stampa a mano il badge della classe energetica (`<span class="badge text-bg-success">`). Il componente `energy-class/badge` fa già la stessa cosa, con la scala corretta e la resa coerente con la scheda immobile.

`EnergyScale::fromArgs()` accetta `['scale' => EnergyScale]` oltre a `['immobile' => object]`, e `EnergyScale::make(string $classe, string $ipe, string $leggeId): ?self` costruisce la scala da una classe sola. Nelle residenze non ci sono IPE né legge di riferimento, quindi si passano stringhe vuote.

Nella sezione di preparazione dati, dopo `$capitolatoUrl = …`, aggiungere:

```php
// Classe energetica: la residenza dichiara solo la classe (niente IPE né legge),
// la scala la deduce da quella. null se il campo è vuoto → il badge non esce.
$energyScale = EnergyScale::make((string) ($row['classe_energetica'] ?? ''), '', '');
```

più `use Wonder\Plugin\Immobili\Support\EnergyScale;` in testa.

Sostituire poi il blocco della card energia:

```php
                <?php if ($energyScale !== null) { ?>
                    <div class="p-4 b-r-15 bg-white b-shadow">
                        <div class="text-small tx-muted"><?= e(__t('pages.residenze.detail.energy')) ?></div>
                        <div class="mt-2"><?php Immobili::component('energy-class/badge', ['scale' => $energyScale]); ?></div>
                    </div>
                <?php } ?>
```

- [ ] **Step 7: Verificare che non resti nessun riferimento vecchio**

```bash
grep -rn "component('features'\|component('filters'\|component('residenze/features'\|component('residenze/card'" src view http config || echo "NESSUN RESIDUO"
grep -n 'text-bg-success' view/pages/frontend/residenze/detail.php || echo "BADGE A MANO RIMOSSO"
```

Expected: `NESSUN RESIDUO` e `BADGE A MANO RIMOSSO`

- [ ] **Step 8: Eseguire la suite**

```bash
find view config -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
bash tests/run.sh | tail -3
```

Expected: `SINTASSI OK`, `TUTTE LE SUITE PASSATE`, smoke a **~179** asserzioni.

- [ ] **Step 9: Commit**

```bash
git add -A view config tests/smoke.php
git commit -m "refactor(immobili): view speculari tra i reparti, specs/amenities in radice"
```

---

### Task 12: Slug localizzati per le residenze

`lang/{it,en}/urls.json` non ha chiavi `residenze`: le route residenze non hanno slug localizzato mentre gli immobili sì (`en/properties`).

**Scelta di traduzione**: `residenze` → `developments` in inglese. È il termine di settore per le nuove costruzioni (*new developments*), coerente con `properties` già scelto per gli immobili. Se il committente preferisce `residences`, cambiare qui e basta: le chiavi restano le stesse.

**Files:**
- Modify: `lang/it/urls.json`
- Modify: `lang/en/urls.json`
- Test: `tests/smoke.php`

- [ ] **Step 1: Scrivere il test che fallisce**

In `tests/smoke.php`, dopo il blocco della simmetria view:

```php
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
```

- [ ] **Step 2: Eseguire e verificare che fallisca**

Run: `php tests/smoke.php 2>&1 | grep -A6 'Slug localizzati'`
Expected: `✗ lang/it: 'residenze' ha uno slug`.

- [ ] **Step 3: Aggiungere le chiavi**

`lang/it/urls.json` per intero:

```json
{
    "immobili": "it/immobili",
    "immobili/list": "it/immobili",
    "immobili/sold": "it/immobili/venduti",
    "residenze": "it/residenze",
    "residenze/list": "it/residenze"
}
```

`lang/en/urls.json` per intero:

```json
{
    "immobili": "en/properties",
    "immobili/list": "en/properties",
    "immobili/sold": "en/properties/sold",
    "residenze": "en/developments",
    "residenze/list": "en/developments"
}
```

Non si aggiunge `residenze/detail`: come per `immobili/detail`, il dettaglio è una route a `{slug}` e non ha uno slug proprio da localizzare.

- [ ] **Step 4: Eseguire la suite**

```bash
php -r 'foreach (["it","en"] as $l) { json_decode(file_get_contents("lang/$l/urls.json"), true) ?: exit("urls.json $l NON VALIDO\n"); } echo "JSON OK\n";'
bash tests/run.sh | tail -3
```

Expected: `JSON OK`, `TUTTE LE SUITE PASSATE`, smoke a **~191** asserzioni.

- [ ] **Step 5: Commit**

```bash
git add lang/it/urls.json lang/en/urls.json tests/smoke.php
git commit -m "feat(immobili): slug localizzati per le route residenze (it/residenze, en/developments)"
```

---

### Task 13: Versione, changelog e documentazione

Il breaking change va comunicato: un override di view su un path rinominato **non produce errore**, torna semplicemente la view del modulo.

**Files:**
- Modify: `module.json` (versione → `2.0.0`)
- Modify: `CHANGELOG.md`
- Modify: `docs/getting-started/struttura-modulo.md`

- [ ] **Step 1: Bump della versione**

In `module.json`: `"version": "2.0.0"` (era `1.1.0`).

- [ ] **Step 2: Riscrivere la sezione Unreleased del CHANGELOG**

Sostituire il blocco `## [Unreleased]` esistente con:

```markdown
## [2.0.0] - non ancora rilasciato

### ⚠️ Breaking — path delle view e namespace

Il modulo è stato riorganizzato su **due reparti speculari** (immobili e
residenze). I path delle view e alcuni namespace sono cambiati.

**Se il sito sovrascrive delle view** in `custom/modules/immobili/view/…`,
rinominare i file secondo questa tabella. Attenzione: un override su un path
non più esistente **non produce errore**, torna semplicemente la view del
modulo — va cercato a mano.

| prima | dopo |
|---|---|
| `components/card.php` | `components/card.php` (invariato, ma ora riceve `['item' => CardViewModel]`) |
| `components/cards-grid.php` | `components/cards.php` con `'layout' => 'grid'` |
| `components/cards-swiper.php` | `components/cards.php` con `'layout' => 'swiper'` |
| `components/features.php` | `components/specs.php` |
| `components/residenze/features.php` | `components/amenities.php` |
| `components/residenze/card.php` | `components/card.php` (unificata) |
| `components/filters.php` | `components/immobili/filters.php` |
| `pages/frontend/list.php` | `pages/frontend/immobili/list.php` |
| `pages/frontend/detail.php` | `pages/frontend/immobili/detail.php` |
| `pages/frontend/sold.php` | `pages/frontend/immobili/sold.php` |

Invariati: `components/map.php`, `components/energy-class/*`,
`components/residenze/timeline.php`, `pages/frontend/residenze/*`,
`pages/backend/immobili/*`, `layout/frontend/immobili.main.php`.

**Namespace spostati** (rilevanti solo per codice del sito che li referenzia):

| prima | dopo |
|---|---|
| `…\Services\ImmobilePresenter` · `ImmobileQuery` · `ResidenzaPresenter` | `…\Catalog\` |
| `…\Services\ImageProcessor` | `…\Media\` |
| `…\Services\FeedSyncService` · `SyncApiUser` | `…\Sync\` |
| `…\Services\ImmobileSeeder` · `ResidenzaSeeder` | `…\Seeding\` |
| `…\Services\IdealistaExporter` | `…\Export\` |
| `…\Models\{Categoria,Macrotipologia,Tipologia,Regione,Provincia,Comune,Quartiere,QuartiereZona}` | `…\Models\Taxonomy\` |
| `…\Models\{FeedSource,SyncLog,Settings}` | `…\Models\System\` |
| `…\Support\{ImmobileForm,ResidenzaForm}` | `…\Support\Forms\` |

Invariati: `…\Models\Immobile`, `…\Models\Residenza`, `…\Resources\*`,
`…\Support\{Slug,Taxonomy,EnergyScale,AttributeCatalog}`, `…\Feed\*`, `…\Pdf\*`.

**Non cambiano**: nessuna URL, nessun nome di route, nessuna chiave di
traduzione esistente, nessuno schema tabella. Nessuna migrazione DB.

### Aggiunto
- `Catalog\CardViewModel`: forma comune delle card dei due reparti.
- `Media\MediaUrl`: fonte unica per URL di upload e varianti responsive.
- `Sync\ReindexService`: il backfill esce dall'handler HTTP.
- `Support\Forms\FormText`: base condivisa dei form di reparto.
- `http/api/task/_guard.php`: gate di autenticazione unico dei task amministrativi.
- Slug localizzati per le route residenze (`it/residenze`, `en/developments`).
- `tests/run.sh`: esegue tutte le suite del modulo.

### Corretto
- Le etichette del dettaglio immobile (cucina, box auto, arredamento, infissi,
  impianto TV, tipo costruzione, stato manutenzione) erano **hardcoded in
  italiano** nel presenter: ora passano dalle traduzioni e si vedono anche in
  inglese.
- `construction_type` codice `255` puntava a `standard` ("Civile") invece che
  a `other` ("Altro"), duplicando il codice `2`.
- La card di lista mostrava la superficie grezza (`120`) invece di quella
  formattata (`120 mq`).

### Rimosso
- `src/Services/` (sciolta per responsabilità).
- `ResidenzaForm::uniqueSlug()` → usare `Slug::fromParts(..., Residenza::class, ..., 'residenza')`.
- `components/cards-grid.php`, `components/cards-swiper.php`, `components/residenze/card.php`.
```

Mantenere invariata sotto la sezione `## [1.0.0]` esistente. Il reparto Residenze descritto nella vecchia `[Unreleased]` va spostato dentro la nuova sezione `### Aggiunto` di `[2.0.0]`, non perso.

- [ ] **Step 3: Aggiornare l'albero in struttura-modulo.md**

In `docs/getting-started/struttura-modulo.md` sostituire il blocco ```` ``` ```` iniziale con l'albero aggiornato:

```
immobili/
├── module.json              Manifesto (slug, entrypoint, path, route, permessi)
├── composer.json            Pacchetto + autoload (Wonder\Plugin\Immobili\ → src/)
├── context.php              Stato condiviso (locale, feed attivi, filtri correnti)
├── config/
│   ├── module.php           Boot: registra lang + provider di default
│   ├── permissions.php      Permessi backend/api del modulo
│   └── routes/              route.frontend / route.backend / route.api
├── src/
│   ├── Immobili.php         Entrypoint ModuleInterface (view/component/layout/context)
│   ├── helpers.php          Funzioni globali immobili*()
│   ├── Models/              Immobile, Residenza, + Taxonomy/ e System/
│   ├── Resources/           CRUD backend (Immobile, Residenza, FeedSource, SyncLog, Settings)
│   ├── Catalog/             Presenter, query e CardViewModel dei due reparti
│   ├── Media/               MediaUrl, ImageProcessor
│   ├── Sync/                FeedSyncService, SyncApiUser, ReindexService
│   ├── Seeding/             ImmobileSeeder, ResidenzaSeeder
│   ├── Export/              IdealistaExporter
│   ├── Feed/                FeedProvider, ProviderRegistry, Getrix, Gestim, DTO
│   ├── Pdf/                 Schede e cartelli stampabili
│   └── Support/             Slug, Taxonomy, EnergyScale, AttributeCatalog, Forms/
├── http/
│   ├── api/task/            sync · images · seed · residenze-seed · reindex (+ _bearer, _guard)
│   ├── api/frontend/        search.php (ricerca JSON della lista)
│   ├── frontend/            idealista.php, immobile/pdf/*
│   └── backend/             feed/sync.php, sync-log/download.php
├── view/
│   ├── components/          card · cards · specs · amenities · map · energy-class/
│   │                        + immobili/filters · residenze/timeline
│   ├── pages/frontend/      immobili/{list,detail,sold} · residenze/{list,detail}
│   ├── pages/backend/       immobili/{form,show}
│   └── layout/frontend/     immobili.main.php
├── lang/it · lang/en        Traduzioni (pages/components/forms/urls/notifications)
├── resources/assets/        CSS e JS del modulo
├── tests/                   Suite standalone (bash tests/run.sh)
└── docs/                    Questa documentazione
```

Aggiornare anche la sezione "Concetti chiave" sostituendo la voce `ImmobilePresenter` con:

```markdown
- **Reparti**: il modulo gestisce due tipologie speculari — **immobili**
  (da feed o manuali) e **residenze** (cantieri, sempre manuali). La regola di
  collocazione è: *radice = trasversale, sottocartella = reparto*, sia in
  `src/` sia in `view/`.
- **Presenter** (`Catalog/`): arricchiscono le righe per le view. `CardViewModel`
  appiattisce le differenze tra i due reparti nella forma comune consumata da
  `components/card.php`.
```

- [ ] **Step 4: Verifica finale complessiva**

```bash
php -r 'json_decode(file_get_contents("module.json"), true) ?: exit("module.json NON VALIDO\n"); echo "module.json OK\n";'
php -r '$m = json_decode(file_get_contents("module.json"), true); echo $m["version"] === "2.0.0" ? "VERSIONE OK\n" : "VERSIONE ERRATA\n";'
find src http view config tests -name '*.php' -exec php -l {} \; | grep -v 'No syntax errors' || echo "SINTASSI OK"
composer dump-autoload
bash tests/run.sh | tail -3
grep -rn 'Immobili\\Services\\' src http view config tests || echo "NESSUN NAMESPACE VECCHIO"
grep -rn "component('features'\|component('filters'\|cards-grid\|cards-swiper" src view http config || echo "NESSUN COMPONENTE VECCHIO"
```

Expected: tutti OK, `TUTTE LE SUITE PASSATE`.

- [ ] **Step 5: Commit**

```bash
git add module.json CHANGELOG.md docs/getting-started/struttura-modulo.md
git commit -m "docs(immobili): versione 2.0.0, changelog con tabella di migrazione, struttura aggiornata"
```

---

## Riepilogo dei commit attesi

| # | commit | tipo |
|---|---|---|
| 1 | runner unico delle suite | test |
| 2 | scioglie Services/, sottocartelle Taxonomy/System/Forms | refactor (solo spostamenti) |
| 3 | Slug parametrica sul modello | refactor |
| 4 | MediaUrl fonte unica di URL e varianti | refactor |
| 5 | FormText base condivisa dei form | refactor |
| 6 | etichette del dettaglio dalle traduzioni | **fix (cambia output)** |
| 7 | gate unico dei task API | refactor |
| 8 | ReindexService | refactor |
| 9 | card unica su CardViewModel | **refactor (cambia output: superficie formattata)** |
| 10 | componente cards unico | refactor |
| 11 | view speculari, specs/amenities | refactor |
| 12 | slug localizzati residenze | feat |
| 13 | versione 2.0.0, changelog, docs | docs |

## Verifica manuale finale (richiede un sito con DB)

I test del modulo girano senza database e non coprono il rendering reale. Dopo il merge, su `immobili.test`:

1. `/immobili/` — griglia, filtri, mappa, paginazione; la superficie nelle card mostra "mq".
2. `/immobili/{slug}/` — scheda con `specs`, classe energetica, mappa.
3. `/en/properties/{slug}/` — le etichette di cucina, box auto, arredamento, infissi e stato **sono in inglese** (prima erano in italiano).
4. `/residenze/` e `/residenze/{slug}/` — card, timeline, `amenities`, immobili collegati.
5. Backend: form immobile e form residenza si aprono e salvano; le select a cascata (categoria → macrotipologia → tipologia) funzionano.
6. `/api/immobili/seed/` e `/api/immobili/residenze-seed/` rispondono in locale.
7. `/api/immobili/search/?q=...` restituisce lo stesso JSON di prima (contratto invariato).
