# Paginazione lista immobili con `pagination()` del framework (refactor SQL)

- **Data**: 2026-07-15
- **Modulo**: `wonder-image/immobili`
- **Stato**: design approvato, in attesa di piano di implementazione

## Obiettivo

Usare in `view/pages/frontend/list.php` la funzione di paginazione di
`wonder-image/app` (`pagination()`, `app/function/frontend/plugin/pagination.php`)
al posto del pager manuale scritto a mano.

## Contesto e vincolo tecnico

`pagination($table, $query, $row, $scroll)` è **SQL-first**: conta le righe con
`sqlCount($table, $query)`, produce un `->limit` SQL e rende l'HTML dei bottoni
(finestra con frecce, primo/ultimo, ellissi). Legge la pagina corrente da
`$_GET['page']` e costruisce gli href da `$PAGE->url` / `$PAGE->query` con il
parametro `page`. Il layer SQL del framework (`Wonder\Sql\Query::Select/Count`)
opera su **una sola tabella, senza join**; le condizioni possono essere una
stringa SQL raw (`"col >= 100 AND ..."`) oltre che un array.

Oggi `Services/ImmobileQuery::search()` filtra e pagina **in PHP**: carica tutte
le righe visibili, le presenta come card, filtra/ordina/slice in PHP. Tre filtri
operano su valori **derivati** che non sono colonne di `immobili`:

- `q` (testo libero) → cerca su `nome` + *nome tipologia* + *nome comune* + indirizzo composto
- `comune` → nome comune (tassonomia `Comune` o JSON `attributi`, fallback Gestim)
- `tipologia` → nome tipologia (idem)

Gli altri filtri (`contratto`, `prezzo_*`, `superficie_*`, `camere`, `bagni`,
`ordina`) mappano già su colonne dirette.

Perché `pagination()` conti correttamente, **tutti** i filtri attivi devono
stare in un `WHERE` su colonne di `immobili`. Quindi si denormalizzano i tre
valori derivati in colonne.

Bug collaterale corretto da questo lavoro: `list.php:75` usa `$pageUrl(...)`, una
closure **mai definita** nel modulo → fatal error con più di una pagina.

## Decisioni (approvate)

1. **Denormalizzare** `comune_nome`, `tipologia_nome`, `ricerca` su `immobili`.
2. **Mappa**: mostrare **tutti** i risultati filtrati (query GeoJSON leggera
   separata), preservando l'UX attuale.
3. **Parametro pagina**: passare da `pagina` a **`page`** (canonico del framework).
4. Includere anche `sold.php` (stesso pager manuale, stesso cambio di parametro).
5. Backfill dei record esistenti tramite task bearer `reindex`.

## Modello dati

In `Models/Immobile.php`, nel gruppo "Derivati persistiti" (accanto a
`dir`/`url`/`qrcode`), aggiungere a `tableSchema()` e `dataSchema()`:

- `comune_nome` — `text()`
- `tipologia_nome` — `text()`
- `ricerca` — `text()` (blob lowercase per la ricerca libera)

Pseudo-indici (`tablePseudos()`) su `comune_nome`, `tipologia_nome`, `prezzo`,
`superficie` per la performance dei filtri/ordinamenti.

Applicazione schema: `php forge update --local`.

## Popolamento dei campi derivati

Metodo pubblico unico `ImmobilePresenter::searchFields(array $row): array`
(ritorna `['comune_nome' => .., 'tipologia_nome' => .., 'ricerca' => ..]`) che,
data una riga in forma array, calcola i tre valori con la **stessa logica** di
presenter/`buildDir` (fallback JSON Gestim incluso). Vive nel presenter perché
riusa `comuneName()` e la logica indirizzo di `prettyAddress`. Sia il sync sia il
backfill gli passano una riga con le chiavi necessarie (`provider`, `tipologia_id`,
`comune_id`, `attributi`, `nome`, `strada`, `indirizzo`, `civico`, `pub_*`):

- `comune_nome` = `ImmobilePresenter::comuneName()` (già esistente)
- `tipologia_nome` = `Taxonomy::tipologiaNome()` con fallback su `attributi['tipologia']`
- `ricerca` = `strtolower(trim(...))` di `nome + tipologia_nome + comune_nome + indirizzo`,
  dove l'indirizzo rispetta i flag `pub_indirizzo`/`pub_civico` come `prettyAddress`

Punti di scrittura:

- **Sync** (`Services/FeedSyncService`): scrive i tre campi su create/update.
  `buildDir()` risolve già comune/tipologia: riusare quei valori.
- **Backfill** (`http/api/task/reindex.php`, bearer, in parità con `sync`/`seed`/`images`):
  routine **idempotente** che itera `immobili` e ripopola i tre campi. Sicura da rieseguire.

## `ImmobileQuery` → builder SQL

`ImmobileQuery` diventa un builder su singola tabella. Metodi:

- `where(array $filters, bool $sold): string` — condizione SQL raw
- `order(string $ordina): array{0: string, 1: string}` — colonna/e + direzione
- `cards(array $rows): array` — presentazione (già esistente)
- `geojson(string $where): array` — Feature GeoJSON su **tutti** i match
- `search(array $filters, int $page, int $perPage, bool $sold): array` — wrapper
  per l'API (usa `where()` + `sqlCount()` + slice `"$offset, $perPage"` + `geojson()`),
  restituisce `items/total/pages/page/geojson` **senza HTML**
- `comuni(bool $sold): array` — diventa `SELECT DISTINCT comune_nome` ordinato

### Mapping filtro → WHERE

Base sempre presente: `visible='true' AND deleted='false' AND sold='<false|true>'`.

| Filtro | Condizione SQL |
|---|---|
| `q` | `LOWER(ricerca) LIKE '%<q>%'` |
| `comune` | `LOWER(comune_nome) LIKE '%<comune>%'` |
| `tipologia` | `LOWER(tipologia_nome) LIKE '%<tipologia>%'` |
| `contratto=A` | `UPPER(contratto_id)='A'` |
| `contratto=V` | `UPPER(contratto_id)<>'A'` (Vendita = tutto ciò che non è A) |
| `prezzo_min` | `(UPPER(trattativa_riservata)='TRUE' OR prezzo=0 OR prezzo>=<min>)` |
| `prezzo_max` | `(UPPER(trattativa_riservata)='TRUE' OR prezzo=0 OR prezzo<=<max>)` |
| `superficie_min` | `(superficie=0 OR superficie>=<min>)` |
| `superficie_max` | `(superficie=0 OR superficie<=<max>)` |
| `camere` | `n_camere>=<n>` |
| `bagni` | `n_bagni>=<n>` |

Le condizioni sono fedeli al comportamento PHP attuale, in particolare:
- Vendita = ogni `contratto_id` diverso da `A` (non solo `'V'`).
- Trattativa riservata e prezzo 0 non vengono mai esclusi dal filtro prezzo.
- Superficie 0 non viene mai esclusa dal filtro superficie.
- `camere`/`bagni` escludono i valori inferiori (nessuna guardia sullo zero).

### Ordinamento

`evidence` sempre per primo (evidenza in cima), poi il criterio scelto:

| `ordina` | ORDER BY |
|---|---|
| `recenti` (default) | `evidence DESC, id DESC` |
| `prezzo_asc` | `evidence DESC, prezzo ASC` |
| `prezzo_desc` | `evidence DESC, prezzo DESC` |
| `superficie_desc` | `evidence DESC, superficie DESC` |

Si usa l'idioma getrix: `$order = "evidence DESC, <col>"`, `$direction = "<ASC|DESC>"`
passati a `pagination()`/`sqlSelect()`.

### Sicurezza (critico)

Il passaggio a SQL reintroduce la superficie di injection che il filtraggio PHP
evitava. Tutti i valori stringa da input utente (`q`, `comune`, `tipologia`)
vanno **escaped** con `$mysqli->real_escape_string()` (idioma del framework,
global `$mysqli`), **inclusi** i wildcard `%` e `_` (escape con `\`) per
preservare la semantica substring dei filtri odierni. I valori numerici
(`prezzo_*`, `superficie_*`, `camere`, `bagni`) vanno castati a `int`.

## Flusso view / API

### `list.php`

```
$query      = new ImmobileQuery();
$where      = $query->where($filters, false);
[$ord,$dir] = $query->order($filters['ordina'] ?? 'recenti');
$PAGINATION = pagination('immobili', $where, $perPage);
$rows       = sqlSelect('immobili', $where, $PAGINATION->limit, $ord, $dir)->row;
$items      = $query->cards($rows);
$geojson    = $query->geojson($where);
// render card, map($geojson), echo $PAGINATION->html
```

`pagination()` preserva automaticamente gli altri parametri della query string
(i filtri attivi) e appende `page=` (vedi `pagination.php:46-62`), quindi non
serve l'argomento `$scroll`; lo scroll-to-list resta eventuale/fuori scope. Il
conteggio avviene una sola volta (dentro `pagination()`); `list.php` non chiama
più `search()`. Il bug `$pageUrl` sparisce.

### `sold.php`

Stessa orchestrazione con filtri vuoti e `sold = true`, sostituendo il pager
manuale con `pagination()` e il parametro `page`.

### API `http/api/frontend/search.php`

Continua a chiamare `$query->search(...)` → JSON `items/total/pages/page/geojson`,
**senza** HTML. `pagination()` resta confinata alle view. Aggiornare la lettura
del parametro pagina da `pagina` a `page`.

## Parametro pagina: `pagina` → `page`

Aggiornare la lettura in `context.php:50`, `list.php`, `sold.php` e `search.php`.
`page` è il parametro letto/prodotto da `pagination()`.

## Componenti e confini

- `ImmobileQuery` — costruzione WHERE/ORDER, conteggio, presentazione card,
  GeoJSON. Unica fonte della logica di query. Testabile in isolamento (input:
  filtri → output: frammento SQL / risultati).
- `ImmobilePresenter::searchFields()` — unica fonte del calcolo dei tre campi
  derivati; usato da sync e backfill.
- `pagination()` (framework) — solo rendering HTML + finestra + limit, nelle view.
- View (`list.php`, `sold.php`) — orchestrano builder + `pagination()` + render.

## Testing

- **Unit `where()`**: ogni filtro → frammento SQL atteso, incluso escaping di
  `%`/`_`/apici e cast numerico; combinazioni di filtri; casi trattativa
  riservata, contratto `V` vs `A`, superficie/prezzo 0.
- **Unit `order()`**: ogni `ordina` → coppia `[order, direction]` attesa.
- **Parità comportamentale**: su un dataset noto (seeder), i risultati e i
  conteggi del nuovo percorso SQL coincidono con il vecchio filtraggio PHP per
  casi rappresentativi.
- **Regressione bug**: `list.php` con più pagine non va più in fatal error.

## Fuori scope

- Nessun cambiamento alla UI dei filtri (`components/filters.php`) oltre a quanto
  necessario per il parametro pagina.
- Nessuna modifica al layer SQL del framework (nessun join, nessuna nuova API).

## File toccati (previsti)

- `src/Models/Immobile.php` — 3 colonne + pseudo-indici
- `src/Services/ImmobileQuery.php` — builder SQL
- `src/Services/FeedSyncService.php` — popolamento campi derivati al sync
- `src/Services/ImmobilePresenter.php` — metodo pubblico `searchFields(array $row)` condiviso
- `http/api/task/reindex.php` — backfill idempotente (nuovo)
- `view/pages/frontend/list.php` — uso di `pagination()` + `page`
- `view/pages/frontend/sold.php` — uso di `pagination()` + `page`
- `http/api/frontend/search.php` — parametro `page`
- `context.php` — parametro `page`
- `tests/` — unit + parità
