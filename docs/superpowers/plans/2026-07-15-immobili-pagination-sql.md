# Paginazione lista immobili con `pagination()` del framework (refactor SQL) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sostituire il pager manuale (e rotto) di `list.php`/`sold.php` con la funzione `pagination()` di `wonder-image/app`, rendendo `ImmobileQuery` un builder SQL su singola tabella e denormalizzando i campi derivati necessari ai filtri.

**Architecture:** `ImmobileQuery` costruisce un `WHERE` SQL raw (single-table, no join) da usare con `pagination()` + `sqlSelect()`. I filtri su nome comune/tipologia/testo libero diventano SQL-esprimibili grazie a tre colonne denormalizzate (`comune_nome`, `tipologia_nome`, `ricerca`) popolate al sync e via backfill. `pagination()` vive solo nelle view; l'API continua a usare `search()` (JSON, no HTML).

**Tech Stack:** PHP 8.2, `wonder-image/app` (framework), MySQL via `Wonder\Sql\Query` (`sqlSelect`/`sqlCount`/`pagination`), test smoke framework-free (`php tests/smoke.php`).

## Global Constraints

- PHP `^8.2`; `declare(strict_types=1)` non usato nei file view/service esistenti — **non** introdurlo dove assente, mantenere lo stile del file.
- Ogni file PHP toccato deve passare `php -l`.
- Input utente in SQL: **sempre** escaped (`$mysqli->real_escape_string`, con escape dei wildcard LIKE `%`/`_`/`\`); numerici castati a `int`.
- Stringhe user-facing via `__t(...)`; URL via `__r(...)`/`__u(...)`. Nessun testo hardcoded nuovo.
- Non modificare `vendor/`. Il parametro pagina canonico del framework è `page`.
- Fedeltà comportamentale col filtraggio PHP attuale (vedi tabella WHERE nella spec).
- Test pure via `php tests/smoke.php` (exit 0). Parti DB-bound: verifica manuale documentata.

**Spec di riferimento:** `docs/superpowers/specs/2026-07-15-immobili-pagination-sql-design.md`.

---

## File Structure

- `src/Services/ImmobileQuery.php` — builder SQL: `where()`, `order()`, `geojson()`, `cards()`, `search()` (wrapper API), `comuni()`. Responsabile di TUTTA la logica di query.
- `src/Services/ImmobilePresenter.php` — aggiunge `searchFields(array $row): array` (unica fonte del calcolo dei 3 derivati).
- `src/Models/Immobile.php` — 3 colonne + pseudo-indici.
- `src/Services/FeedSyncService.php` — popola i 3 derivati su create/update.
- `http/api/task/reindex.php` — backfill idempotente (nuovo).
- `view/pages/frontend/list.php` — orchestrazione `pagination()` + `page`.
- `view/pages/frontend/sold.php` — idem.
- `http/api/frontend/search.php` — parametro `page`.
- `context.php` — parametro `page`.
- `tests/smoke.php` — asserzioni su `order()`, `where()`, `searchFields()`.

---

## Task 1: `ImmobileQuery::order()` (pura)

**Files:**
- Modify: `src/Services/ImmobileQuery.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Produces: `ImmobileQuery::order(string $ordina): array` → `[string $order, string $direction]`.

- [ ] **Step 1: Scrivi il test che fallisce** — in `tests/smoke.php`, dopo la sezione `ProviderRegistry`, prima del riepilogo finale:

```php
echo "ImmobileQuery::order\n";
$Q = new \Wonder\Plugin\Immobili\Services\ImmobileQuery();
$assert($Q->order('recenti') === ['evidence DESC, id', 'DESC'], "recenti => id DESC");
$assert($Q->order('prezzo_asc') === ['evidence DESC, prezzo', 'ASC'], "prezzo_asc");
$assert($Q->order('prezzo_desc') === ['evidence DESC, prezzo', 'DESC'], "prezzo_desc");
$assert($Q->order('superficie_desc') === ['evidence DESC, superficie', 'DESC'], "superficie_desc");
$assert($Q->order('boh') === ['evidence DESC, id', 'DESC'], "default => recenti");
```

- [ ] **Step 2: Esegui il test, deve fallire**

Run: `php tests/smoke.php`
Expected: FAIL su `Call to undefined method ...::order()` (o Fatal). L'exit code ≠ 0.

- [ ] **Step 3: Implementa `order()`** — in `src/Services/ImmobileQuery.php`, aggiungi il metodo (dopo `search()`):

```php
    /**
     * Colonna/e e direzione di ordinamento per la query SQL. `evidence` è sempre
     * primo (immobili in evidenza in cima), poi il criterio scelto.
     *
     * @return array{0: string, 1: string} [orderBy, direction]
     */
    public function order(string $ordina): array
    {
        return match ($ordina) {
            'prezzo_asc'      => ['evidence DESC, prezzo', 'ASC'],
            'prezzo_desc'     => ['evidence DESC, prezzo', 'DESC'],
            'superficie_desc' => ['evidence DESC, superficie', 'DESC'],
            default           => ['evidence DESC, id', 'DESC'],
        };
    }
```

- [ ] **Step 4: Esegui il test, deve passare**

Run: `php tests/smoke.php`
Expected: PASS (tutte le asserzioni verdi, exit 0).

- [ ] **Step 5: Lint**

Run: `php -l src/Services/ImmobileQuery.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add src/Services/ImmobileQuery.php tests/smoke.php
git commit -m "feat(immobili): ImmobileQuery::order() per ordinamento SQL"
```

---

## Task 2: `ImmobileQuery::where()` + escaping (pura, fallback offline)

**Files:**
- Modify: `src/Services/ImmobileQuery.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Consumes: nessuno.
- Produces:
  - `ImmobileQuery::where(array $filters, bool $sold = false): string`
  - privati: `sqlEscape(string $v): string`, `like(string $v): string`.

Note escaping: `sqlEscape()` usa `$GLOBALS['mysqli']->real_escape_string()` se disponibile, altrimenti `addslashes()` (permette il test offline; per gli input di test l'output coincide). `like()` fa prima l'escape dei wildcard LIKE (`\`, `%`, `_`) poi `sqlEscape()`.

- [ ] **Step 1: Scrivi i test che falliscono** — in `tests/smoke.php`, dopo la sezione `ImmobileQuery::order`:

```php
echo "ImmobileQuery::where\n";
$w = $Q->where([], false);
$assert(str_contains($w, "`visible` = 'true'"), "base: visible true");
$assert(str_contains($w, "`deleted` = 'false'"), "base: deleted false");
$assert(str_contains($w, "`sold` = 'false'"), "base: sold false (lista)");
$assert(str_contains($Q->where([], true), "`sold` = 'true'"), "base: sold true (venduti)");

$w = $Q->where(['q' => 'Roma'], false);
$assert(str_contains($w, "LOWER(`ricerca`) LIKE '%roma%'"), "q => LIKE lowercase");

$w = $Q->where(['q' => '50%'], false);
$assert(str_contains($w, "LOWER(`ricerca`) LIKE '%50\\\\%%'"), "q: wildcard % escaped");

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

// SQL injection: apice escaped
$assert(str_contains($Q->where(['comune' => "O'Brien"], false), "LIKE '%o\\'brien%'"), "apice escaped");
```

- [ ] **Step 2: Esegui il test, deve fallire**

Run: `php tests/smoke.php`
Expected: FAIL su `Call to undefined method ...::where()`.

- [ ] **Step 3: Implementa `where()` + helper** — in `src/Services/ImmobileQuery.php`, aggiungi dopo `order()`:

```php
    /**
     * Costruisce la condizione WHERE (stringa SQL raw) per la ricerca immobili.
     * Single-table su `immobili`. Fedele al filtraggio PHP storico. Tutti i
     * valori stringa sono escaped; i numerici castati a int.
     *
     * @param array<string, mixed> $filters
     */
    public function where(array $filters, bool $sold = false): string
    {
        $clauses = [
            "`visible` = 'true'",
            "`deleted` = 'false'",
            "`sold` = '".($sold ? 'true' : 'false')."'",
        ];

        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $clauses[] = "LOWER(`ricerca`) LIKE '%".$this->like($q)."%'";
        }

        $comune = strtolower(trim((string) ($filters['comune'] ?? '')));
        if ($comune !== '') {
            $clauses[] = "LOWER(`comune_nome`) LIKE '%".$this->like($comune)."%'";
        }

        $tipologia = strtolower(trim((string) ($filters['tipologia'] ?? '')));
        if ($tipologia !== '') {
            $clauses[] = "LOWER(`tipologia_nome`) LIKE '%".$this->like($tipologia)."%'";
        }

        $contratto = strtoupper(trim((string) ($filters['contratto'] ?? '')));
        if ($contratto === 'A') {
            $clauses[] = "UPPER(`contratto_id`) = 'A'";
        } elseif ($contratto === 'V') {
            $clauses[] = "UPPER(`contratto_id`) <> 'A'";
        }

        if (($min = (int) ($filters['prezzo_min'] ?? 0)) > 0) {
            $clauses[] = "(UPPER(`trattativa_riservata`) = 'TRUE' OR `prezzo` = 0 OR `prezzo` >= {$min})";
        }
        if (($max = (int) ($filters['prezzo_max'] ?? 0)) > 0) {
            $clauses[] = "(UPPER(`trattativa_riservata`) = 'TRUE' OR `prezzo` = 0 OR `prezzo` <= {$max})";
        }

        if (($min = (int) ($filters['superficie_min'] ?? 0)) > 0) {
            $clauses[] = "(`superficie` = 0 OR `superficie` >= {$min})";
        }
        if (($max = (int) ($filters['superficie_max'] ?? 0)) > 0) {
            $clauses[] = "(`superficie` = 0 OR `superficie` <= {$max})";
        }

        if (($camere = (int) ($filters['camere'] ?? 0)) > 0) {
            $clauses[] = "`n_camere` >= {$camere}";
        }
        if (($bagni = (int) ($filters['bagni'] ?? 0)) > 0) {
            $clauses[] = "`n_bagni` >= {$bagni}";
        }

        return implode(' AND ', $clauses);
    }

    /**
     * Escape di un valore per una clausola LIKE: prima i metacaratteri LIKE
     * (`\`, `%`, `_`) così l'input è trattato come substring letterale, poi
     * l'escape SQL per gli apici.
     */
    private function like(string $value): string
    {
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return $this->sqlEscape($value);
    }

    /**
     * Escape SQL. Usa la connessione mysqli del framework se disponibile;
     * altrimenti (test offline, senza runtime) ricade su addslashes.
     */
    private function sqlEscape(string $value): string
    {
        $mysqli = $GLOBALS['mysqli'] ?? null;

        return $mysqli instanceof \mysqli ? $mysqli->real_escape_string($value) : addslashes($value);
    }
```

- [ ] **Step 4: Esegui il test, deve passare**

Run: `php tests/smoke.php`
Expected: PASS (exit 0). Se l'asserzione `50%` fallisce per conteggio backslash, verifica: `str_replace` produce `50\%`, `addslashes` → `50\\%`, quindi la stringa PHP attesa nel test è `"%50\\\\%%"` (4 backslash nel sorgente = 2 reali). È già così nello Step 1.

- [ ] **Step 5: Lint**

Run: `php -l src/Services/ImmobileQuery.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add src/Services/ImmobileQuery.php tests/smoke.php
git commit -m "feat(immobili): ImmobileQuery::where() builder SQL con escaping"
```

---

## Task 3: `ImmobilePresenter::searchFields()` (pura sul path fallback)

**Files:**
- Modify: `src/Services/ImmobilePresenter.php`
- Test: `tests/smoke.php`

**Interfaces:**
- Produces: `ImmobilePresenter::searchFields(array $row): array` → `['comune_nome' => string, 'tipologia_nome' => string, 'ricerca' => string]`.
- Consumes: metodi esistenti `comuneName()` (pubblico) e `prettyAddress()` (privato, stessa classe), `Taxonomy::tipologiaNome()`, helper `immobiliDecodeJsonArray()`.

- [ ] **Step 1: Scrivi il test che fallisce** — in `tests/smoke.php`, dopo la sezione `ImmobileQuery::where`:

```php
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
$assert(str_contains($sf['ricerca'] ?? '', 'villa'), "ricerca contiene tipologia");
$assert(str_contains($sf['ricerca'] ?? '', 'milano'), "ricerca contiene comune (via indirizzo)");
$assert(str_contains($sf['ricerca'] ?? '', 'via roma'), "ricerca contiene la via");
$assert(($sf['ricerca'] ?? '') === strtolower($sf['ricerca'] ?? ''), "ricerca è lowercase");
```

- [ ] **Step 2: Esegui il test, deve fallire**

Run: `php tests/smoke.php`
Expected: FAIL su `Call to undefined method ...::searchFields()`.

- [ ] **Step 3: Implementa `searchFields()`** — in `src/Services/ImmobilePresenter.php`, aggiungi dopo `comuneName()`:

```php
    /**
     * Campi derivati denormalizzati usati dai filtri SQL (lista frontend):
     * nome comune e tipologia risolti (con fallback JSON Gestim) e un blob di
     * ricerca lowercase. Unica fonte del calcolo, condivisa da sync e backfill.
     *
     * @param array<string, mixed> $row  riga immobile (o campi normalizzati) con
     *   almeno: provider, tipologia_id, comune_id, attributi, nome, strada,
     *   indirizzo, civico, pub_indirizzo, pub_civico
     * @return array{comune_nome: string, tipologia_nome: string, ricerca: string}
     */
    public function searchFields(array $row): array
    {
        $provider = (string) ($row['provider'] ?? '');
        $attributi = immobiliDecodeJsonArray($row['attributi'] ?? []);

        $tipologia = Taxonomy::tipologiaNome($provider, (string) ($row['tipologia_id'] ?? ''));
        if ($tipologia === '') {
            $tipologia = (string) ($attributi['tipologia'] ?? '');
        }

        $comune = $this->comuneName($row, $attributi);

        $nome = (string) ($row['nome'] ?? '');
        $indirizzo = $this->prettyAddress($row, $comune);

        $ricerca = strtolower(trim(implode(' ', array_filter([
            $nome, $tipologia, $indirizzo,
        ]))));

        return [
            'comune_nome'    => $comune,
            'tipologia_nome' => $tipologia,
            'ricerca'        => $ricerca,
        ];
    }
```

- [ ] **Step 4: Esegui il test, deve passare**

Run: `php tests/smoke.php`
Expected: PASS (exit 0).

- [ ] **Step 5: Lint**

Run: `php -l src/Services/ImmobilePresenter.php`
Expected: `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add src/Services/ImmobilePresenter.php tests/smoke.php
git commit -m "feat(immobili): ImmobilePresenter::searchFields() per campi derivati"
```

---

## Task 4: Colonne denormalizzate sul model `Immobile`

**Files:**
- Modify: `src/Models/Immobile.php`

**Interfaces:**
- Produces: colonne `comune_nome`, `tipologia_nome`, `ricerca` e pseudo-indici omonimi + `prezzo`/`superficie`.

Nota: nessun unit test (schema DB). Verifica via `php -l` ora e `php forge update --local` in Task 9 di verifica.

- [ ] **Step 1: Aggiungi le colonne a `tableSchema()`** — in `src/Models/Immobile.php`, nel gruppo "Attributi estesi / polimorfici + derivati persistiti", estendi l'elenco:

```php
                // Attributi estesi / polimorfici + derivati persistiti
                'attributi', 'dir', 'url', 'qrcode',
                'comune_nome', 'tipologia_nome', 'ricerca',
```

- [ ] **Step 2: Aggiungi i Field a `dataSchema()`** — sotto i "Derivati persistiti":

```php
            // Derivati persistiti
            Field::key('dir')->text()->slug(),
            Field::key('url')->text(),
            Field::key('qrcode')->text(),

            // Derivati per la ricerca SQL (denormalizzati al sync)
            Field::key('comune_nome')->text(),
            Field::key('tipologia_nome')->text(),
            Field::key('ricerca')->text(),
```

- [ ] **Step 3: Aggiungi gli pseudo-indici a `tablePseudos()`** — estendi l'array:

```php
            'ind_dir'        => ['index' => 'dir'],
            'ind_comune_nome'    => ['index' => 'comune_nome'],
            'ind_tipologia_nome' => ['index' => 'tipologia_nome'],
            'ind_prezzo'     => ['index' => 'prezzo'],
            'ind_superficie' => ['index' => 'superficie'],
```

- [ ] **Step 4: Lint**

Run: `php -l src/Models/Immobile.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add src/Models/Immobile.php
git commit -m "feat(immobili): colonne denormalizzate comune_nome/tipologia_nome/ricerca"
```

---

## Task 5: Popolamento dei derivati al sync

**Files:**
- Modify: `src/Services/FeedSyncService.php`

**Interfaces:**
- Consumes: `ImmobilePresenter::searchFields(array $row): array` (Task 3).

Contesto: in `FeedSyncService::upsert()` (intorno a `src/Services/FeedSyncService.php:151-153`) i campi `dir`/`url` vengono già assegnati a `$fields` prima di `Immobile::create/update`. Aggiungere lì i 3 derivati, calcolati dalla stessa riga `$fields` (che contiene `provider`, `tipologia_id`, `comune_id`, `attributi`, `nome`, `strada`, `indirizzo`, `civico`, `pub_*`). Verificare i nomi esatti dei campi assemblati leggendo il metodo prima di editare.

- [ ] **Step 1: Assicura la disponibilità del presenter** — in cima alla classe `FeedSyncService`, se non presente, aggiungi un presenter riusabile. Controlla le proprietà esistenti; se non c'è, aggiungi:

```php
    private \Wonder\Plugin\Immobili\Services\ImmobilePresenter $presenter;
```

e nel costruttore (o inizializzazione esistente) istanzialo:

```php
        $this->presenter ??= new \Wonder\Plugin\Immobili\Services\ImmobilePresenter();
```

Se il costruttore non esiste o l'iniezione è scomoda, in alternativa istanzia inline nel punto d'uso: `$presenter = new ImmobilePresenter();` (import namespace già presente nel file o usa il FQCN).

- [ ] **Step 2: Popola i derivati** — subito dopo le righe `$fields['dir'] = $dir; $fields['url'] = $this->buildUrl($dir);`:

```php
        $fields = array_merge($fields, $this->presenter->searchFields($fields));
```

(`searchFields()` legge `attributi` come array o stringa JSON tramite `immobiliDecodeJsonArray`; `$fields['attributi']` qui è già l'array normalizzato del listing, gestito correttamente.)

- [ ] **Step 3: Lint**

Run: `php -l src/Services/FeedSyncService.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Smoke (regressione, deve restare verde)**

Run: `php tests/smoke.php`
Expected: PASS (exit 0).

- [ ] **Step 5: Commit**

```bash
git add src/Services/FeedSyncService.php
git commit -m "feat(immobili): popola i campi derivati di ricerca al sync"
```

---

## Task 6: `ImmobileQuery` — `geojson()`, `cards()`, refactor `search()` e `comuni()` a SQL

**Files:**
- Modify: `src/Services/ImmobileQuery.php`

**Interfaces:**
- Consumes: `where()`, `order()` (Task 1-2), `sqlSelect()`, `sqlCount()` (framework), `ImmobilePresenter::cards()`.
- Produces:
  - `ImmobileQuery::cards(array $rows): array`
  - `ImmobileQuery::geojson(string $where): array`
  - `ImmobileQuery::search(array $filters, int $page, int $perPage, bool $sold = false): array` (stessa shape: `items,total,pages,page,geojson`)
  - `ImmobileQuery::comuni(bool $sold = false): array` (SQL `DISTINCT comune_nome`)

Nota: metodi DB-bound → nessun unit test offline; verifica in Task 9.

- [ ] **Step 1: Aggiungi `cards()` (delega al presenter)** — in `src/Services/ImmobileQuery.php`:

```php
    /**
     * Presenta righe DB grezze come card per la view.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, object>
     */
    public function cards(array $rows): array
    {
        return $this->presenter->cards($this->rows($rows));
    }
```

- [ ] **Step 2: Aggiungi `geojson()`** — costruisce le Feature per TUTTI i match, con una query leggera (niente lookup immagini: il JS mappa non usa `cover`):

```php
    /**
     * Feature GeoJSON per la mappa su TUTTI gli immobili che soddisfano $where.
     * Query leggera: solo le colonne necessarie al popup (id, nome, prezzo,
     * superficie, url, coordinate). Nessun lookup immagini.
     *
     * @return array<int, array<string, mixed>>
     */
    public function geojson(string $where): array
    {
        $cols = 'id, nome, comune_nome, tipologia_nome, strada, prezzo, '
            .'contratto_id, trattativa_riservata, superficie, url, latitudine, longitudine';

        $result = sqlSelect('immobili', $where, null, 'id', 'DESC', $cols);
        $features = [];

        foreach (($result->row ?? []) as $row) {
            $lat = (float) ($row['latitudine'] ?? 0);
            $lng = (float) ($row['longitudine'] ?? 0);
            if ($lat === 0.0 || $lng === 0.0) {
                continue;
            }

            $tipologia = (string) ($row['tipologia_nome'] ?? '');
            $comune = (string) ($row['comune_nome'] ?? '');
            $strada = (string) ($row['strada'] ?? '');
            $name = trim(($tipologia !== '' ? $tipologia : 'Immobile')
                .(($strada !== '' ? $strada : $comune) !== '' ? ' · '.($strada !== '' ? $strada : $comune) : ''));

            $prezzo = immobiliIsTrue($row['trattativa_riservata'] ?? '')
                ? 'Trattativa riservata'
                : immobiliFormatPrice($row['prezzo'] ?? 0);
            if ($prezzo !== '' && strtoupper((string) ($row['contratto_id'] ?? '')) === 'A'
                && !immobiliIsTrue($row['trattativa_riservata'] ?? '')) {
                $prezzo .= '/mese';
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'properties' => [
                    'id'      => (int) ($row['id'] ?? 0),
                    'name'    => $name,
                    'price'   => $prezzo,
                    'surface' => immobiliFormatSurface($row['superficie'] ?? 0),
                    'url'     => (string) ($row['url'] ?? ''),
                    'cover'   => '',
                ],
            ];
        }

        return $features;
    }
```

- [ ] **Step 3: Rifattorizza `search()` a SQL** — sostituisci l'attuale corpo di `search()` con:

```php
    public function search(array $filters, int $page, int $perPage, bool $sold = false): array
    {
        $where = $this->where($filters, $sold);
        [$order, $direction] = $this->order((string) ($filters['ordina'] ?? 'recenti'));

        $total = (int) sqlCount('immobili', $where);
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $offset = ($page - 1) * $perPage;
        $rows = sqlSelect('immobili', $where, "{$offset}, {$perPage}", $order, $direction)->row ?? [];

        return [
            'items'   => $this->cards($rows),
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
            'geojson' => $this->geojson($where),
        ];
    }
```

- [ ] **Step 4: Rifattorizza `comuni()` a SQL** — sostituisci il corpo con una query distinta:

```php
    public function comuni(bool $sold = false): array
    {
        $where = "`visible` = 'true' AND `deleted` = 'false' AND `sold` = '"
            .($sold ? 'true' : 'false')."' AND `comune_nome` <> ''";

        $result = sqlSelect('immobili', $where, null, 'comune_nome', 'ASC', 'DISTINCT `comune_nome`');

        $comuni = [];
        foreach (($result->row ?? []) as $row) {
            $nome = trim((string) ($row['comune_nome'] ?? ''));
            if ($nome !== '') {
                $comuni[$nome] = $nome;
            }
        }

        $comuni = array_values($comuni);
        natcasesort($comuni);

        return array_values($comuni);
    }
```

- [ ] **Step 5: Rimuovi i metodi PHP ora morti** — elimina `matches()`, `sort()`, `amount()` se non più referenziati. Mantieni `rows()` (usato da `cards()`). Verifica assenza di riferimenti:

Run: `grep -nE '(->|::|\b)(matches|sort|amount)\s*\(' src/Services/ImmobileQuery.php`
Expected: nessun riferimento residuo (a parte le definizioni che stai rimuovendo). Rimuovi le definizioni.

- [ ] **Step 6: Lint + smoke**

Run: `php -l src/Services/ImmobileQuery.php && php tests/smoke.php`
Expected: `No syntax errors detected` + smoke PASS (exit 0).

- [ ] **Step 7: Commit**

```bash
git add src/Services/ImmobileQuery.php
git commit -m "refactor(immobili): ImmobileQuery::search/comuni/geojson via SQL"
```

---

## Task 7: `list.php` — `pagination()` del framework + parametro `page`

**Files:**
- Modify: `view/pages/frontend/list.php`
- Modify: `context.php`

**Interfaces:**
- Consumes: `ImmobileQuery::where/order/cards/geojson`, `pagination()` (framework), `sqlSelect()`.

- [ ] **Step 1: Cambia il parametro pagina in `context.php`** — a `context.php:50`:

```php
    'pagina'         => max(1, $paramInt('page')),
```

(La chiave interna resta `pagina` nel array filtri per compatibilità; cambia solo la sorgente GET a `page`.)

- [ ] **Step 2: Riscrivi il corpo PHP e il blocco paginazione di `list.php`** — sostituisci la sezione dati (l'attuale `$query = new ImmobileQuery(); $result = ...; $pageUrl = ...;`) con:

```php
$query = new ImmobileQuery();

$where = $query->where($filters, false);
[$order, $direction] = $query->order((string) ($filters['ordina'] ?? 'recenti'));

$PAGINATION = pagination('immobili', $where, $perPage);
$rows = sqlSelect('immobili', $where, $PAGINATION->limit, $order, $direction)->row ?? [];

$items = $query->cards($rows);
$total = (int) sqlCount('immobili', $where);
$geojson = $query->geojson($where);

Immobili::layout('main');
```

- [ ] **Step 3: Adegua l'uso nelle sezioni HTML** — sostituisci i riferimenti a `$result[...]`:
  - mappa: `if (!empty($geojson)) { ... Immobili::component('map', ['features' => $geojson]); }`
  - conteggio: `<?= $total ?> ...`
  - griglia: `if ($items === []) { ...empty... } else { foreach ($items as $immobile) { Immobili::component('card', ['immobile' => $immobile]); } }`

- [ ] **Step 4: Sostituisci il pager manuale con `pagination()`** — rimpiazza l'intero blocco `<?php if (($result['pages'] ?? 1) > 1) { ... } ?>` con:

```php
        <div class="w-100 d-flex j-content-center mt-8">
            <?= $PAGINATION->html ?>
        </div>
```

- [ ] **Step 5: Lint**

Run: `php -l view/pages/frontend/list.php && php -l context.php`
Expected: `No syntax errors detected` (entrambi).

- [ ] **Step 6: Commit**

```bash
git add view/pages/frontend/list.php context.php
git commit -m "feat(immobili): list.php usa pagination() del framework e parametro page"
```

---

## Task 8: `sold.php` e API `search.php` — parametro `page`

**Files:**
- Modify: `view/pages/frontend/sold.php`
- Modify: `http/api/frontend/search.php`

- [ ] **Step 1: Riscrivi `sold.php` con `pagination()`** — il corpo dati diventa:

```php
$query = new ImmobileQuery();
$where = $query->where([], true);
[$order, $direction] = $query->order('recenti');

$PAGINATION = pagination('immobili', $where, $perPage);
$rows = sqlSelect('immobili', $where, $PAGINATION->limit, $order, $direction)->row ?? [];

$items = $query->cards($rows);
$geojson = $query->geojson($where);

Immobili::layout('main');
```

E sostituisci nelle sezioni HTML `$result['geojson']`→`$geojson`, `$result['items']`→`$items`, e il blocco pager manuale `<?php if (($result['pages'] ?? 1) > 1) { ... } ?>` con:

```php
    <div class="d-flex j-content-center mt-8">
        <?= $PAGINATION->html ?>
    </div>
```

Rimuovi la riga `$action = __r('immobili.sold');` se non più usata altrove nel file.

- [ ] **Step 2: Cambia il parametro pagina nell'API** — in `http/api/frontend/search.php`, la riga:

```php
    $page = max(1, (int) ($params['page'] ?? 1));
```

- [ ] **Step 3: Lint**

Run: `php -l view/pages/frontend/sold.php && php -l http/api/frontend/search.php`
Expected: `No syntax errors detected` (entrambi).

- [ ] **Step 4: Commit**

```bash
git add view/pages/frontend/sold.php http/api/frontend/search.php
git commit -m "feat(immobili): sold.php e API search usano il parametro page"
```

---

## Task 9: Backfill idempotente `http/api/task/reindex.php`

**Files:**
- Create: `http/api/task/reindex.php`
- Read (per il pattern): `http/api/task/images.php`, `http/api/task/_bearer.php`

**Interfaces:**
- Consumes: `ImmobilePresenter::searchFields()`, `Immobile::find()`, `Immobile::update()`.

- [ ] **Step 1: Leggi il pattern esistente** — apri `http/api/task/images.php` e `http/api/task/_bearer.php` per replicare header, protezione bearer, forma della risposta e registrazione della route.

Run: `sed -n '1,40p' http/api/task/images.php`

- [ ] **Step 2: Crea `http/api/task/reindex.php`** — replica il pattern di `images.php`; il corpo itera gli immobili e ripopola i 3 campi (idempotente):

```php
<?php

// Backfill idempotente dei campi derivati di ricerca (comune_nome,
// tipologia_nome, ricerca) su tutti gli immobili esistenti. Sicuro da
// rieseguire. Protetto da bearer come gli altri task. Adeguare gli import,
// la firma dell'handler e la forma della risposta a images.php.

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

// ... (bearer + registrazione route come images.php) ...

    $presenter = new ImmobilePresenter();
    $rows = Immobile::find(['deleted' => 'false']);
    $rows = is_array($rows) ? (isset($rows['id']) ? [$rows] : array_values($rows)) : [];

    $updated = 0;
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        Immobile::update($presenter->searchFields($row), $id);
        $updated++;
    }

    // risposta nello stile images.php, es.: ['success' => true, 'updated' => $updated]
```

Adegua esattamente firma/handler/risposta a `images.php` (namespace `Handler::run(...)` o equivalente osservato).

- [ ] **Step 3: Lint**

Run: `php -l http/api/task/reindex.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add http/api/task/reindex.php
git commit -m "feat(immobili): task bearer reindex per backfill campi derivati"
```

---

## Task 10: Verifica di integrazione (ambiente utente) + rimozione hotfix

**Files:**
- Verifica end-to-end; nessuna nuova modifica salvo cleanup.

Questi passi richiedono il runtime/DB del sito e vanno eseguiti nell'ambiente dell'utente.

- [ ] **Step 1: Applica lo schema**

Run (root del sito che monta il modulo, o del modulo se previsto): `php forge update --local`
Expected: le colonne `comune_nome`, `tipologia_nome`, `ricerca` e gli indici vengono creati su `immobili`.

- [ ] **Step 2: Backfill dei record esistenti** — chiama il task `reindex` (bearer) come per `sync`/`images` (stessa modalità/token usata dagli altri task). In alternativa esegui una sincronizzazione feed, che ora popola i campi.
Expected: risposta `success: true`, `updated: N` = numero di immobili non cancellati.

- [ ] **Step 3: Carica la lista** — apri `/immobili/` con risultati su più pagine.
Expected: nessun fatal error; il pager del framework (frecce/finestra) è renderizzato; il numero risultati è coerente.

- [ ] **Step 4: Verifica filtri + paginazione** — applica un filtro (es. `comune`) e naviga a pagina 2.
Expected: URL con `?comune=...&page=2` (filtri preservati); i conteggi/pagine rispecchiano il filtro; la mappa mostra tutti i match filtrati.

- [ ] **Step 5: Verifica venduti** — apri `/immobili/venduti/` (o rotta `immobili.sold`) su più pagine.
Expected: pager del framework OK, `page` come parametro.

- [ ] **Step 6: Verifica API** — `GET /api/immobili/search/?comune=...&page=2`.
Expected: JSON con `items/total/pages/page/geojson` coerenti col filtro.

- [ ] **Step 7: Rimuovi la hotfix interim** — la closure `$pageUrl` in `list.php` è già stata rimossa nella riscrittura di Task 7; conferma con:

Run: `grep -n 'pageUrl' view/pages/frontend/list.php`
Expected: nessun risultato.

- [ ] **Step 8: Smoke finale**

Run: `php tests/smoke.php`
Expected: PASS (exit 0).

- [ ] **Step 9: Aggiorna il CHANGELOG** — aggiungi una voce sotto la sezione corrente di `CHANGELOG.md` (rispetta il formato esistente) che descrive: paginazione via `pagination()` del framework, refactor SQL di `ImmobileQuery`, colonne denormalizzate + task `reindex`, cambio parametro `pagina`→`page`.

- [ ] **Step 10: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs(immobili): changelog refactor paginazione SQL"
```

---

## Self-Review (esito)

- **Copertura spec:** modello (Task 4), popolamento sync (Task 5), backfill (Task 9), builder `where`/`order`/`geojson`/`search`/`comuni` (Task 1,2,6), sicurezza escaping (Task 2), viste + `page` (Task 7,8), mappa tutti-i-match (Task 6), test (Task 1-3 + smoke), verifica (Task 10). ✔
- **Placeholder:** l'unico punto "da adeguare al pattern osservato" è Task 9 (handler/bearer di `reindex` modellato su `images.php`) — richiede lettura del file esistente allo step 1, che è parte del task. Nessun TODO/TBD nel codice.
- **Coerenza tipi:** `where(): string`, `order(): array{0:string,1:string}`, `searchFields(): array{comune_nome,tipologia_nome,ricerca}`, `geojson(string): array`, `cards(array): array`, `search(...): array{items,total,pages,page,geojson}` — usati coerentemente in Task 6,7,8.
