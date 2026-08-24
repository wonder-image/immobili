# Design — Reparto Residenze (Cantieri / Costruzioni)

Data: 2026-08-24
Modulo: `wonder-image/immobili`
Stato: approvato (design), pronto per il piano di implementazione.

## Obiettivo

Aggiungere al modulo immobiliare un **reparto Residenze**: le strutture fatte
costruire dall'agenzia (o di cui l'agenzia gestisce tutte/la maggior parte delle
unità), sul modello di `bgstar.org/cantieri`. Nel legacy bgstar-org il reparto è
statico/hard-coded (`cantieri/index.php` + `cantiere/{slug}/index.php`): lo
ricostruiamo come **modulo DB-backed**, editabile dal backend, con lista e
scheda frontend, coerente con le convenzioni del modulo Immobili.

> Nome scelto dall'utente: **Residenze** (non "Cantieri"), applicato in modo
> uniforme a codice, tabelle, route, backend e frontend.

Il reparto Residenze è un **fratello di Immobili** dentro lo stesso pacchetto:
riusa Model/Resource, `RepeaterRelation`, componenti, mappa Google (GeoJSON),
`Support\Slug`, le tassonomie (`Comune`), il layout `Immobili::layout('main')` e
le regole di styling (solo utility di `wonder-image/lib` + token sito, nessun CSS
custom salvo asset mappa già esistenti).

## Decisioni prese con l'utente (2026-08-24)

1. **Immobili collegati** → relazione **1 residenza → N immobili** via FK
   `residenza_id` sulla tabella `immobili` (nessun pivot). Un immobile appartiene
   al massimo a una residenza. Selezione dal form della residenza (multi-select
   degli immobili esistenti); nella scheda immobile compare il link alla residenza.
2. **Unità abitative** → **solo numero totale** (campo numerico sintetico). Le
   tipologie di appartamento sono coperte dagli immobili collegati.
3. **Link sito** → la **pagina interna `/residenze/{slug}/` è sempre generata**;
   se `sito_url` è presente appare come CTA aggiuntiva ("Visita il sito"). Il
   link esterno non sostituisce mai la pagina interna.
4. **Features** → **catalogo condiviso definito in codice + lang** (multi-select,
   salvato come JSON di id). Non una tabella/Resource gestibile dall'admin.
   Migrabile a taxonomy DB in futuro senza rompere lo schema.
5. **Lingua contenuti** → **solo italiano** per ora (come il legacy), senza
   modello it/en separato. Se in futuro servisse il bilingue si aggiunge un
   `ResidenzaDescrizione` sul modello di `ImmobileDescrizione`.
6. **Cover** → **nessun campo dedicato**: la copertina è la **prima immagine**
   della gallery (prima per `position`), derivata in lettura.
7. **Comune** → **FK `comune_id`** verso la tassonomia `immobili_comuni`
   (Model `Comune`), non testo libero. `comune_nome` denormalizzato al salvataggio
   (come Immobili) per display/ricerca.

## Approccio scelto

**Modulo fratello che rispecchia Immobili.** `Residenza` come Model + Resource
pieni, tabella figlia `ResidenzaImmagine` per la gallery (riusa webp/varianti
responsive e riordino del framework; la prima immagine funge da cover), frontend
`/residenze/` + `/residenze/{slug}/`, componenti e mappa riusati da Immobili.
Scartati: variante minimale (gallery come JSON di filename → perde resize/riordino)
e modello generico "progetti" (over-engineering, YAGNI).

## Modello dati

### Nuovo Model `Residenza` — tabella `immobili_residenze`

Convenzioni identiche a `Immobile`: `dataSchema()` con `Field::key(...)`,
`tableSchema()` con lunghezze VARCHAR/tipi espliciti, `tablePseudos()` per gli
indici, `decorate()` per gli URL e i derivati.

Colonne:

| Campo | Tipo dataSchema | SQL / note |
|---|---|---|
| `code` | `text()->uniqueCode('res_')` | VARCHAR(32) |
| `nome` | `text()->sanitizeFirst()` | TEXT, required nel form |
| `slug` | `text()->slug()` | VARCHAR(191), indice, unico via `Support\Slug` |
| `logo` | `image()` | filename JSON; opzionale |
| `sito_url` | `text()->sanitize(false)` | TEXT; validato http/https lato Resource |
| `inizio_anno` | `number()->decimals(0)` | INT |
| `inizio_mese` | `number()->decimals(0)` | INT nullable (1–12, opzionale) |
| `fine_anno` | `number()->decimals(0)` | INT (fine stimata) |
| `fine_mese` | `number()->decimals(0)` | INT nullable (opzionale) |
| `descrizione_breve` | `text()` | TEXT |
| `descrizione_lunga` | `text()` | TEXT |
| `indirizzo` | `text()` | TEXT |
| `civico` | `text()` | VARCHAR(32) |
| `cap` | `text()` | VARCHAR(16) |
| `comune_id` | `number()->decimals(0)` | INT(10), FK → `immobili_comuni`, SET NULL, nullable |
| `comune_nome` | `text()` | VARCHAR(191), denormalizzato al salvataggio |
| `latitudine` | `text()` | VARCHAR(32) |
| `longitudine` | `text()` | VARCHAR(32) |
| `zoom` | `text()` | VARCHAR(8) |
| `classe_energetica` | `text()` | VARCHAR(16); riusa `ImmobileForm::energyClasses()` |
| `unita_abitative` | `number()->decimals(0)` | INT |
| `features` | `json()` | array di id dal catalogo lang |
| `capitolato` | `file()` (accept pdf) | filename JSON; singolo PDF |
| `sold` | `text()` | VARCHAR(5) — "Venduto tutto" |
| `stato` | `text()` | VARCHAR(16) — override editoriale (`in_arrivo`/`in_corso`/`completato`) |
| `visible` | `text()` | VARCHAR(5), default `true` |
| `evidence` | `text()` | VARCHAR(5), default `false` |
| `position` | `number()->decimals(0)` | INT — ordinamento lista |

`FK_COLUMNS` (come Immobile): `'comune_id' => 'immobili_comuni'` → INT(10),
nullable, FK `SET NULL`.

`tablePseudos()`: indici su `slug`, `visible`, `sold`, `position`, `comune_id`,
`comune_nome`.

`decorate()`: aggiunge `url` = `__r('residenze.detail', ['slug' => $slug])`,
deriva `cover` = prima immagine della gallery (per `position`), e normalizza i
campi file (logo, capitolato) come già fa Immobile per i media.

### Tabella figlia `ResidenzaImmagine` — `immobili_residenze_immagini`

Rispecchia `ImmobileImmagine` ma senza i campi di feed. Campi:
`residenza_id` (INT, indice), `upload` (`image()->maxSize(3)->extensions([...])`
con webp/varianti automatiche del framework), `titolo` (`text()->sanitizeFirst()`),
`position` (INT, indice). Gestita dalla Resource via `RepeaterRelation`
(`positionKey('position')`, `softDelete(false)`, `model(ResidenzaImmagine::class)`).
**La prima immagine (per `position`) è la cover** usata in lista e come hero.

### Modifica a `Immobile` — nuova colonna `residenza_id`

- `dataSchema()`: `Field::key('residenza_id')->number()->decimals(0)`.
- `tableSchema()`: aggiunta a `FK_COLUMNS` → `'residenza_id' => 'immobili_residenze'`
  (INT(10), nullable, FK `SET NULL`).
- `tablePseudos()`: `ind_residenza` su `residenza_id`.
- `FeedSyncService`: `residenza_id` va **preservato** come flag manuale a ogni
  sync (come `visible`/`evidence`/`sold`), così i feed non lo azzerano.
- `decorate()`: opzionale `residenza_url`/`residenza_nome` derivati per il link
  "Parte della residenza: …" nella scheda immobile (lookup leggero).

> Migrazione dati esistente: `php forge update` aggiunge la colonna
> `residenza_id` e crea le tabelle `immobili_residenze` /
> `immobili_residenze_immagini`. Richiede il DB dell'utente (ambiente locale non
> ha accesso a MySQL di Herd). Nessun backfill necessario (default NULL).

## Catalogo Features (in lang)

Definito in codice + lang, sul modello delle opzioni immobili
(`ImmobileForm::options(...)`). Un helper `ResidenzaForm::features()` restituisce
`id => label` leggendo le chiavi da `lang/{it,en}` (es.
`components.residenze.features.*`). Ogni voce ha un'icona Bootstrap associata
(mappa `id => icona` in codice) per il rendering frontend. Nel form: multi-select
(checkTree o selectSearch multiplo) che salva un array di id in `features` (JSON).
Voci iniziali proposte (poi affinabili): ascensore, giardino, box/posto auto,
domotica, fotovoltaico, climatizzazione, area verde comune, videosorveglianza,
cantina, terrazzo.

## Backend — `ResidenzaResource`

CRUD completo, convenzioni identiche a `ImmobileResource`.

- `path()` = `residenze`; `icon()` = `bi bi-buildings`.
- `permissionSchema()`: backend `['list','create','store','edit','update','delete']`
  per `['admin','immobili_manager']` (nessun permesso nuovo).
- `navigationSchema()`: sezione **"Residenze"** (riusa la sezione `immobili` o
  titolo dedicato) sotto `immobili_manager`.
- `apiSchema()`: disabilitata (come Immobili).
- `formLayoutSchema()` a card:
  - *Dati principali*: nome, descrizione_breve, descrizione_lunga.
  - *Timeline*: inizio_anno, inizio_mese, fine_anno, fine_mese.
  - *Localizzazione*: indirizzo, civico, cap, `comune_id`
    (`selectSearch(ImmobileForm::municipalities())`), latitudine, longitudine, zoom.
  - *Media*: logo, gallery (repeater relation a `ResidenzaImmagine`; la prima è
    la cover — nota UI nel label).
  - *Immobili collegati*: `selectSearch(immobili, multiple: true)`.
  - *Features*: multi-select dal catalogo lang.
  - *Energia & unità*: classe_energetica (`energyClasses()`), unita_abitative.
  - *Capitolato*: file PDF singolo.
  - *Stato & pubblicazione*: sito_url, stato, sold, evidence, visible, position + Submit.
- `tableSchema()` lista backend: cover (prima immagine), nome, comune_nome,
  timeline (inizio→fine), n° immobili collegati, badge stato/sold,
  visible/evidence, azioni.
- `tableLayoutSchema()`: ricerca su `nome`/`comune_nome`, filtro stato.
- `mutateRequestValues`: risolve `comune_nome` da `comune_id`
  (`ImmobileForm::taxonomyLabel(Comune::class, comune_id)`) e genera lo slug
  con `Support\Slug` se assente.

### Immobili collegati — sincronizzazione FK

Gestita in `mutateRequestValues`/hook post-store della Resource:
1. Leggi gli id selezionati nel campo `immobili_collegati` (virtuale, non colonna
   di `immobili_residenze`; rimosso da `stripRelationInputValues`).
2. `SET residenza_id = {id residenza}` sugli immobili selezionati.
3. `SET residenza_id = NULL` sugli immobili che erano collegati e non lo sono più.
Operazioni idempotenti; in edit si confronta lo stato precedente. Nessun pivot.
In `edit`, il valore iniziale del multi-select è `SELECT id FROM immobili WHERE
residenza_id = {residenza}`.

## Frontend

Route registrate in `config/routes/route.frontend.php`, gruppo `residenze.`:

```
Route::name('residenze.')->prefix('/residenze')->group(function () {
    Route::get('/',        Immobili::viewPath('pages/frontend/residenze/list.php'))->name('list');
    Route::get('/{slug}/', Immobili::viewPath('pages/frontend/residenze/detail.php'))->name('detail'); // ultima del gruppo
});
```

### `/residenze/` — lista

Blocchi immagine+testo (riusa lo stile alternato del legacy) con: cover (prima
immagine), logo (o nome), chip timeline, badge "Venduto tutto"/"In corso"/"In
arrivo"/"Completato", descrizione breve, CTA che porta **sempre** alla pagina
interna. Se `sito_url` presente, CTA extra "Visita il sito". Ordinati per
`position`. Solo residenze `visible = true`.

### `/residenze/{slug}/` — dettaglio

Carica la residenza per slug (`visible=true`), 404/redirect a `residenze.list` se
assente. Sezioni:
- **Hero-swiper** sulla gallery (`Dependencies::swiper()` + `fancyapps()`,
  stesso pattern di `detail.php` immobili). La prima immagine è la cover; se la
  gallery è vuota, niente hero.
- **Logo + descrizione lunga**.
- **Timeline** orizzontale: `inizio_anno[/mese]` → `fine_anno[/mese]`, con
  indicatore "oggi". Solo anno (+ mese se valorizzato). Componente
  `view/components/residenze/timeline.php`, solo utility lib.
- **Features** con icone (dal catalogo lang).
- **Unità abitative** (numero) e **classe energetica** (badge).
- **Download capitolato** (se presente).
- **Immobili collegati**: `SELECT ... WHERE residenza_id = {id} AND visible='true'`,
  griglia che riusa `Immobili::component('card', ['immobile' => ...])`.
- **Mappa** Google via `Immobili::component('map', ['features' => [pointGeoJson]])`
  se `latitudine`/`longitudine` presenti.
- **CTA "Visita il sito"** se `sito_url` presente.

### Scheda immobile

In `view/pages/frontend/detail.php`: se `residenza_id` valorizzato, mostra un link
"Parte della residenza: {nome}" verso `residenze.detail`.

### Stato derivato

`in_arrivo` se oggi < inizio; `in_corso` se inizio ≤ oggi ≤ fine; `completato`
se oggi > fine; `sold=true` → "Venduto tutto" (prevale). Il campo `stato`
permette l'override manuale. Logica in un presenter/helper residenze.

## i18n

`lang/it` + `lang/en`: label form (`labelSchema`), sezioni/titoli, pulsanti,
stringhe frontend (lista, dettaglio, timeline, CTA) e **catalogo features**
(`components.residenze.features.*`). Contenuti dei singoli record solo in
italiano (nessun modello descrizione it/en).

## Componenti / file nuovi

- `src/Models/Residenza.php`, `src/Models/ResidenzaImmagine.php`
- `src/Resources/ResidenzaResource.php`
- `src/Support/ResidenzaForm.php` (catalogo features, options, energy/comuni reuse),
  eventuale `src/Services/ResidenzaPresenter.php` (cover, stato, timeline, immagini)
- `view/pages/frontend/residenze/list.php`, `.../detail.php`
- `view/components/residenze/card.php`, `.../timeline.php`, `.../features.php`
- `view/pages/backend/residenze/*` se serve override form/show (altrimenti generici)
- Aggiornamenti: `src/Models/Immobile.php` (colonna `residenza_id`),
  `src/Services/FeedSyncService.php` (preserva `residenza_id`),
  `view/pages/frontend/detail.php` (link alla residenza),
  `config/routes/route.frontend.php`, `lang/it/*`, `lang/en/*`.

## Non incluso (YAGNI / fuori scope)

- Import residenze da feed/gestionale (sempre manuali).
- Descrizioni bilingue it/en (rimandate).
- Tassonomia features gestibile da DB/Resource (catalogo lang per ora).
- PDF/scheda cartello della residenza (solo il capitolato caricato dall'admin).
- Sezione legacy "Altri cantieri" (solo nomi) — esclusa.
- Migrazione dei progetti esistenti (nessun dato pregresso in DB).

## Criteri di successo

- L'admin crea/modifica una residenza dal backend: dati, timeline, logo, gallery
  multipla (prima = cover), capitolato PDF, features, classe energetica, unità,
  comune (da tassonomia), immobili collegati.
- Selezionando immobili dal DB, la loro FK `residenza_id` viene impostata e
  rimossa correttamente; i feed non la sovrascrivono.
- `comune_id` è salvato dalla tassonomia e `comune_nome` denormalizzato.
- `/residenze/` elenca le residenze visibili; `/residenze/{slug}/` mostra la
  scheda completa con timeline, gallery, features, immobili collegati e mappa.
- Se `sito_url` è presente, la pagina interna esiste comunque e mostra la CTA
  esterna.
- La scheda immobile linka alla residenza di appartenenza.
- Nessun CSS custom nuovo; solo utility `wonder-image/lib` + token sito.
- `php -l` pulito su tutti i file; smoke test del modulo verdi.
