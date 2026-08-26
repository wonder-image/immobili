# Design — Riorganizzazione del modulo su due reparti (Immobili / Residenze)

Data: 2026-08-26
Modulo: `wonder-image/immobili`
Stato: approvato (design), pronto per il piano di implementazione.

## Obiettivo

Il modulo nasce monoreparto: gli **immobili** occupano la radice di ogni cartella
come se fossero l'unico dominio. Con l'arrivo delle **residenze** (2026-08-24) il
secondo reparto è stato innestato in sottocartelle, producendo un'organizzazione
asimmetrica in cui la posizione di un file non dice più a quale reparto
appartiene.

Questa riorganizzazione rende i due reparti **speculari**, scioglie il calderone
`src/Services/` e rimuove sei duplicazioni che l'assetto attuale nascondeva.

Non aggiunge funzionalità. L'unico cambiamento di comportamento previsto è
intenzionale ed è la correzione della monolingua del dettaglio immobile
(punto 4 delle deduplicazioni).

## Decisioni prese con l'utente (2026-08-26)

1. **Ampiezza** → simmetria piena, **breaking accettato**. La 1.1.0 non è ancora
   rilasciata: è il momento giusto per pagare il costo una volta sola. Nessun
   layer di retrocompatibilità sui path delle view.
2. **Criterio `src/`** → **per strato, reparto nelle sottocartelle**. I namespace
   di `Models\Immobile`, `Models\Residenza` e `Resources\*` restano invariati
   perché sono l'API che i siti referenziano.
3. **Deduplicazioni** → incluse **anche quelle che cambiano comportamento**
   (dizionari del presenter → traduzioni).
4. **Nessuna cartella `shared/`** → la radice di `view/components/` *è* già il
   livello condiviso. Criterio: **radice = trasversale, sottocartella = reparto**.
5. **Componenti unificati dove il contenuto è lo stesso**: `cards-grid` +
   `cards-swiper` → un solo `cards`; le due `card` → una sola, alimentata da un
   view-model comune. Le due `features` **non** si unificano: sono due contenuti
   diversi (vedi § Unificazione dei componenti).

## Stato attuale (il problema, in una tabella)

|                  | immobili                                          | residenze                              |
|------------------|---------------------------------------------------|----------------------------------------|
| pagine frontend  | `view/pages/frontend/{list,detail,sold}.php`      | `view/pages/frontend/residenze/{list,detail}.php` |
| componenti       | `view/components/{card,features,filters,…}.php`   | `view/components/residenze/{card,features,timeline}.php` |
| form helper      | `Support/ImmobileForm.php`                        | `Support/ResidenzaForm.php` (metà proxy) |
| presenter        | `Services/ImmobilePresenter.php` (849 righe)      | `Services/ResidenzaPresenter.php`      |
| seeder           | `Services/ImmobileSeeder.php`                     | `Services/ResidenzaSeeder.php`         |
| slug             | `Support/Slug.php` (accoppiato a `Immobile`)      | `ResidenzaForm::uniqueSlug()` (riscritto) |

## Struttura di destinazione

### `view/`

```
view/
├── layout/frontend/immobili.main.php          invariato
├── components/
│   ├── cards.php          unificato: layout grid | swiper
│   ├── card.php           unificato: view-model comune
│   ├── specs.php          ex features.php — attributo → valore
│   ├── amenities.php      ex residenze/features.php — icona + label
│   ├── map.php
│   ├── energy-class/      energy-class · badge · line · scale
│   ├── immobili/          filters
│   └── residenze/         timeline
└── pages/
    ├── frontend/
    │   ├── immobili/  list · detail · sold
    │   └── residenze/ list · detail
    └── backend/
        └── immobili/  form · show
```

`map` ed `energy-class` restano in radice perché già usati da entrambi i reparti:
la mappa da `immobili/list`, `immobili/detail`, `immobili/sold` e
`residenze/detail`; `energy-class` è costruito su `EnergyScale::fromArgs()`, che
accetta indifferentemente un immobile o una scala. Effetto collaterale
desiderabile: gli override dei siti su questi due path **non si rompono**.

Dopo l'unificazione le due sottocartelle di reparto contengono **un file
ciascuna**. Restano comunque, perché il criterio deve essere uno solo in tutto
`view/` (dove `pages/` le usa in pieno) e perché è lì che va ogni componente di
reparto che nascerà.

Come parte del lavoro, `residenze/detail` smette di stampare a mano il badge
della classe energetica e passa al componente `energy-class/badge`.

### `src/`

```
src/
├── Immobili.php · helpers.php
├── Models/
│   ├── Immobile · ImmobileImmagine · ImmobileDescrizione · Residenza
│   ├── Taxonomy/  Categoria · Macrotipologia · Tipologia · Regione
│   │              Provincia · Comune · Quartiere · QuartiereZona
│   └── System/    FeedSource · SyncLog · Settings
├── Resources/     invariata
├── Catalog/       ImmobilePresenter · ImmobileQuery · ResidenzaPresenter
│                  CardViewModel (nuovo — forma comune delle card)
├── Media/         ImageProcessor · MediaUrl (nuovo)
├── Sync/          FeedSyncService · SyncApiUser · ReindexService (nuovo)
├── Seeding/       ImmobileSeeder · ResidenzaSeeder
├── Export/        IdealistaExporter
├── Feed/          invariata
├── Pdf/           invariata
└── Support/       Slug · Taxonomy · EnergyScale · AttributeCatalog
                   └── Forms/  FormText (nuovo) · ImmobileForm · ResidenzaForm
```

`src/Services/` viene eliminata: conteneva sette classi con sette responsabilità
diverse ed era di fatto la cartella "tutto il resto".

`Models\Immobile` e `Models\Residenza` **non** si spostano: sono l'API pubblica
verso i siti. Si spostano solo le otto tassonomie e i tre modelli di sistema, che
nessun sito referenzia direttamente.

**Vincolo verificato**: `ModelRegistry` e `ResourceRegistry` del framework usano
`RecursiveDirectoryIterator` e ricavano il FQCN dal `namespace` dichiarato nel
file. Le sottocartelle di `Models/` sono quindi sicure, a patto che il namespace
PSR-4 segua il path.

## Unificazione dei componenti

### `cards-grid` + `cards-swiper` → `cards.php`

I due componenti sono già la stessa cosa: entrambi accettano `immobili`
(`object[]`), filtrano `is_object`, escono su lista vuota, risolvono la **stessa**
`card`, accumulano le classi extra passate. Divergono solo nel wrapper finale —
`<div class="d-grid col-3 …">` contro `__swiper()`.

Esiste inoltre una **terza copia non dichiarata**: `residenze/list.php` scrive a
mano `<div class="d-grid col-3 col-p-1 gap-5 mt-4">` + `foreach`, perché
`cards-grid` filtra `is_object` mentre le residenze passano array grezzi.

→ un solo `components/cards.php` con `layout: 'grid' | 'swiper'` (default `grid`),
che assorbe anche gli argomenti oggi esclusivi dello swiper (`id`, `slide_class`,
`aria_label`), ignorati in modalità griglia. `residenze/list.php` lo usa al posto
della griglia scritta a mano.

### Le due `card` → `card.php`

I gusci HTML sono già identici carattere per carattere:

```html
<a class="d-block b-r-15 o-hidden bg-white tx-black b-shadow" href="…">
  <div class="f-3-2 p-r bg-cover o-hidden" style="background-image:url(…)">
    <span class="p-a badge …" style="top:.6rem;left:.6rem">…</span>
  <div class="p-4 d-grid gap-2">
```

Divergono solo per il **tipo di input** (immobili: object già decorato da
`ImmobilePresenter::card()`; residenze: array grezzo + presenter passato come
argomento della view) e per quali righe compongono il corpo.

Unificare il file senza unificare il contratto dati produrrebbe un componente con
`if (residenza) … else …` dentro: i due file di oggi in un file solo, meno
leggibili. Il lavoro è quindi **a monte**, nei presenter, che devono produrre la
stessa forma:

```php
object {
    url:       string,
    cover:     string,
    badge:     ?object { label: string, variant: string },
    eyebrow:   string,   // "Trilocale · Vendita"  |  ""
    title:     string,   // prettyName             |  nome
    subtitle:  string,   // prettyAddress          |  comune_nome
    highlight: string,   // prettyPrezzo           |  ""
    excerpt:   string,   // ""                     |  descrizione_breve
    meta:      array<int, object { icon: string, text: string }>,
}
```

`highlight` ed `excerpt` sono i due slot che oggi divergono (il prezzo ce l'ha
solo l'immobile, la descrizione breve solo la residenza) e sono entrambi
opzionali.

**Criterio di accettazione del refactor**: dentro `card.php` non deve comparire
nessun `if` sul reparto — solo `if` sulla presenza del dato. Se serve un ramo per
tipo, il view-model è sbagliato e va corretto lì.

Conseguenza: `ResidenzaPresenter` smette di essere passato dentro la view come
argomento (`['residenza' => $row, 'presenter' => $presenter]`); la pagina passa
il view-model già pronto, come fa già la lista immobili.

### Le due `features` → restano due, rinominate

Non sono lo stesso contenuto reso diversamente:

|        | `features.php` (immobili)                          | `residenze/features.php`            |
|--------|----------------------------------------------------|-------------------------------------|
| dato   | coppie **attributo → valore** ("Superficie: 120 mq") | **presenze booleane** ("Giardino")  |
| fonte  | `AttributeCatalog` + Settings, configurabile da backend | catalogo fisso in codice (`FEATURE_ICONS`) |
| resa   | 4 colonne, label sopra / valore sotto, bordo         | 2 colonne, icona + label            |

Un componente unico dovrebbe accettare sia `{label, value}` sia `{icon, label}` e
scegliere il layout in base a quale dei due riceve: conterrebbe entrambi i
componenti attuali **più** la logica per distinguerli.

→ restano due, ma rinominati per dire cosa sono, ed entrambi in radice perché
entrambi trasversali per natura:

- `features.php` → **`specs.php`** (attributo → valore)
- `residenze/features.php` → **`amenities.php`** (icona + label)

Nota per il futuro (**fuori scope**): l'immobile ha già le sue dotazioni booleane
(piscina, camino, allarme, videocitofono, porta blindata…) che oggi finiscono in
`specs` come stringhe "Sì". Quelle sono lo stesso contenuto delle features
residenze e potranno passare ad `amenities` senza scrivere un componente nuovo.
Non si fa in questa passata: cambierebbe l'aspetto della scheda immobile.

## Le sei deduplicazioni

### 1 — Gate di autenticazione dei task API

`http/api/task/{seed,residenze-seed,reindex}.php` ripetono ~25 righe identiche:
rilevamento ambiente locale, estrazione del Bearer (header o `?token=`),
risposta 403.

→ `http/api/task/_guard.php` che espone `immobiliTaskGuard(string $label): void`,
speculare all'esistente `_bearer.php`. I tre handler lo richiedono in una riga.

### 2 — Slug univoco

`Slug::unique()` interroga `Immobile` per costante; `ResidenzaForm::uniqueSlug()`
riscrive la stessa logica su `Residenza`.

→ `Slug` diventa parametrica sul modello, con `Immobile` come default:

```php
Slug::base(array $parts, string $fallback = 'immobile'): string
Slug::unique(string $base, string $modelClass = Immobile::class, int|string|null $excludeId = null): string
Slug::fromParts(array $parts, string $modelClass = Immobile::class, int|string|null $excludeId = null, string $fallback = 'immobile'): string
```

`ResidenzaForm::uniqueSlug()` **sparisce**. I suoi due chiamanti —
`ResidenzaResource:409` e `ResidenzaSeeder:115` — passano a
`Slug::fromParts([$nome], Residenza::class, $excludeId, 'residenza')`.
Il fallback diventa parametro perché oggi è hardcoded a `'immobile'` in
`Slug::base()`, mentre le residenze usano `'residenza'`.

### 3 — URL immagini e varianti responsive

`ResidenzaPresenter::{imageUrl,previewUrl,firstFile}` e
`ImmobilePresenter::{variants,cover}` ricalcolano entrambi la base di upload e il
suffisso `-620.webp`.

→ `Media/MediaUrl` con `url(string $file, string $folder)`,
`preview(string $file, string $folder)`, `firstFile(mixed $stored)`. Entrambi i
presenter la usano; la cartella arriva dal `Model::$folder`.

### 4 — Dizionari di dominio doppi (cambia comportamento)

`ImmobilePresenter` dichiara otto costanti con etichette **italiane hardcoded**
(`KITCHEN`, `GARAGE`, `FURNISHING`, `WINDOW_FRAMES`, `TV_SYSTEM`,
`CONSTRUCTION_TYPE`, `MAINTENANCE_STATE`, …) che duplicano
`ImmobileForm::OPTION_KEYS`, il quale invece risolve via `__t()`. Il dettaglio
immobile è quindi monolingua italiano anche sul sito in inglese.

→ le costanti spariscono, il presenter legge da `ImmobileForm`. **Effetto
atteso e voluto**: quelle etichette diventano tradotte in EN. Le chiavi
corrispondenti esistono già in `lang/en/forms.json`; la verifica che siano
complete fa parte del lavoro.

### 5 — `ResidenzaForm` per metà proxy

`energyClasses()`, `municipalities()`, `comuneNome()` inoltrano a `ImmobileForm`:
il segnale che manca un livello condiviso.

→ `Support/Forms/FormText` con la risoluzione dei testi (`forms.<reparto>.<key>`
con fallback difensivo), le classi energetiche e le tassonomie comuni. I due form
di reparto tengono solo ciò che è effettivamente loro.

### 6 — Logica di dominio in un handler HTTP

`reindex.php` contiene il backfill degli slug e dei campi di ricerca.

→ `Sync/ReindexService::run(): array`. L'handler scende a ~15 righe: guard,
chiamata, JSON.

### Extra — `urls.json`

`lang/{it,en}/urls.json` non ha chiavi `residenze`: le route residenze non hanno
slug localizzato mentre gli immobili sì (`en/properties`). Si aggiungono
`residenze`, `residenze/list`, `residenze/detail`.

## Cosa non cambia

- **Le URL**, tutte: `/api/immobili/{sync,images,seed,residenze-seed,reindex,search}/`
  e le route frontend. Sono contratto verso i cron dei siti e verso il push di
  Gestim; i path dei file dietro sono liberi, gli endpoint no.
- **I nomi delle route** (`immobili.list`, `residenze.detail`, …) e le **chiavi di
  traduzione**.
- **`module.json`** nella parte `paths` / `routes` / `database`.
- La firma di `Immobili::component()`, `viewPath()`, `layout()`, `renderPage()`.
- Gli **schemi tabella**: nessuna migrazione DB, nessun reseed.

## Verifica

I sei file in `tests/` girano senza database (`php tests/<file>.php`) e coprono
schema Residenza, form delle Resource, slug, prezzi di lista, scala energetica e
PDF. Sono la rete di sicurezza del refactor: devono passare **prima** e **dopo**,
con lo stesso esito.

Aggiunte previste:
- `tests/smoke.php` — `Slug::unique()` con `$modelClass` esplicito.
- `tests/smoke.php` — `MediaUrl::preview()` / `firstFile()` su valori JSON,
  array già decodificato, stringa legacy, URL assoluto.
- `tests/smoke.php` — il view-model card prodotto da `ImmobilePresenter` e da
  `ResidenzaPresenter` espone **le stesse chiavi**, con i campi non pertinenti a
  stringa vuota anziché assenti. È il test che protegge il criterio "nessun `if`
  sul reparto dentro `card.php`".
- `tests/residenze.php` — la struttura dei componenti residenze punta ai nuovi path.

Più: `php -l` su ogni file toccato, `composer dump-autoload` dopo lo spostamento
delle classi, e un `grep` finale per riferimenti residui ai path vecchi.

## Costo del breaking change

`module.json` passa a **2.0.0**. Il `CHANGELOG.md` riporta la tabella completa
path vecchio → path nuovo, sia per le view (override dei siti in
`custom/modules/immobili/view/…`) sia per le classi spostate.

Il rischio specifico da comunicare: un override di view su un path rinominato
**non produce errore**, torna semplicemente la view del modulo. Va cercato a
mano, per questo la tabella nel changelog è parte della consegna e non un extra.
