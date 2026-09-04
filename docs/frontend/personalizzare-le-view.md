# Personalizzare le view

Tutte le view e i componenti del modulo sono **sovrascrivibili dal sito** senza toccare il pacchetto.

## Override di un componente o di una pagina

Crea lo stesso file sotto `custom/modules/immobili/view/` nel sito. Ha priorità su quello del modulo.

Esempi:

```
custom/modules/immobili/view/components/immobili/card-base.php     → sostituisce la card base degli immobili
custom/modules/immobili/view/components/residenze/card-base.php    → sostituisce la card base delle residenze
custom/modules/immobili/view/pages/frontend/immobili/list.php      → sostituisce la lista immobili
custom/modules/immobili/view/pages/frontend/residenze/list.php     → sostituisce la lista residenze
custom/modules/immobili/view/layout/frontend/immobili.main.php     → sostituisce il layout
```

Il meccanismo è gestito da `Immobili::viewPath()`, che controlla prima l'override del sito.

## Componenti disponibili

La regola di collocazione è **radice = trasversale, sottocartella = reparto**: un componente
che serve entrambi i reparti sta in `components/`, uno specifico sta in `components/immobili/`
o `components/residenze/`.

| Componente | Argomenti |
| ---------- | --------- |
| `immobili/card-base` · `card-overlay` · `card-overlay-rich` | `['immobile' => object, 'gallery' => bool]` |
| `immobili/cards-grid` | `['immobili' => object[], 'card' => 'card-base', 'gallery' => bool, 'card_args' => [], 'class' => 'mt-4']` |
| `immobili/cards-swiper` | come la griglia, più `id`, `slide_class`, `aria_label` |
| `residenze/card-base` · `card-overlay` · `card-overlay-rich` | `['residenza' => array, 'presenter' => ResidenzaPresenter, 'gallery' => bool]` |
| `residenze/cards-grid` | `['residenze' => array[], 'presenter' => ResidenzaPresenter, 'card' => 'card-base', 'gallery' => bool, 'card_args' => [], 'class' => 'mt-4']` |
| `residenze/cards-swiper` | come la griglia, più `id`, `slide_class`, `aria_label` |
| `specs` | `['immobile' => $immobile]` — coppie attributo → valore |
| `amenities` | `['features' => ['ascensore', 'giardino', …]]` — icona + etichetta |
| `map` | `['features' => $geojson, 'zoom' => 15, 'mapId' => 'id-opzionale']` |
| `energy-class/badge` · `line` · `scale` | `['immobile' => $immobile]` oppure `['scale' => EnergyScale]` |
| `immobili/filters` | `['filters' => [...], 'action' => $url]` |
| `residenze/timeline` | `['inizio' => '03/2025', 'fine' => '2026', 'stato' => '…']` |

Richiama sempre la card nel namespace del suo reparto:

```php
Immobili::component('immobili/card-base', ['immobile' => $immobile]);
Immobili::component('residenze/card-base', [
    'residenza' => $residenza,
    'presenter' => $presenter,
]);
```

### Varianti di card

Ogni reparto possiede le proprie card e legge direttamente i propri dati. Non
c'è un dispatcher comune: il nome della variante coincide con il nome del file.
Un sito può quindi sostituire una card, oppure aggiungerne una nuova, senza
creare un view-model condiviso fra immobili e residenze.

| file | com'è | quando |
| ---- | ----- | ------ |
| `card-base.php` (default) | immagine sopra, corpo su fondo chiaro | liste lunghe e colonne strette: il testo sta su fondo pieno, il contrasto non dipende dalla foto |
| `card-overlay.php` | immagine a tutta card, testo essenziale sovrapposto in basso | quando la foto è il contenuto e serve impatto |
| `card-overlay-rich.php` | come overlay, con più informazioni sintetiche | griglie larghe, dove la card ha spazio per reggere più righe sopra la foto |

```php
<?php Immobili::component('immobili/cards-grid', [
    'immobili' => $immobili,
    'card' => 'card-overlay',
    'gallery' => true,
]); ?>
```

Il gradiente scuro delle varianti `overlay` non è decorativo: è ciò che rende
leggibile il testo sopra una foto qualsiasi. Se lo togli in un override, il
titolo sparisce sulle immagini chiare.

Per aggiungere, ad esempio, `card-compact.php`, crealo nella cartella del
reparto (nel modulo o nell'override del sito) e passa `'card' => 'card-compact'`
a `cards-grid` o `cards-swiper` dello stesso reparto. Se la nuova card richiede
opzioni proprie, passale in `card_args`: il contenitore le inoltra a ogni card,
ma conserva sempre il dato nativo corretto (`immobile` o `residenza`).

### Gallery dentro la card

`'gallery' => true` rende sfogliabili le immagini senza aprire la scheda. È
indipendente dalla card scelta e compare solo quando il dato nativo porta più
di un'immagine. Ogni reparto possiede il proprio `card-media.php`.

Non usa Swiper: in una griglia servirebbe un'istanza per card. Sono CSS più un
listener delegato (`resources/assets/js/immobili-card.js`), caricati solo quando
la gallery è effettivamente attiva.

Da dove arrivano le immagini:

- **residenze** — sempre disponibili: stanno nella colonna JSON già letta con la
  riga, quindi la gallery non costa nulla in più.
- **immobili** — le foto vivono in tabella figlia. `ImmobilePresenter::card()`
  **non** le carica, perché sarebbe una query per riga di lista. Chi vuole la
  gallery sugli immobili valorizza `$immobile->images` a monte, con una sola
  query per l'intera pagina; senza, la card mostra la sola cover.

### Dati nativi e collezioni

Le card non richiedono una conversione intermedia. Gli immobili usano gli
oggetti prodotti da `ImmobileQuery::cards()`; le residenze usano le righe del
modello e il loro `ResidenzaPresenter`:

```php
$immobili = $query->cards($rows);
$residenze = $rows;
$presenter = new ResidenzaPresenter();
```

Ogni reparto espone un componente distinto per ciascun layout:

```php
<?php Immobili::component('immobili/cards-grid', [
    'immobili' => $immobili,
    'class' => 'mt-4',
]); ?>

<?php Immobili::component('immobili/cards-swiper', [
    'immobili' => $immobili,
    'card' => 'card-overlay-rich',
    'id' => 'immobili-in-evidenza',
    'class' => 'mt-6',
    'aria_label' => __t('pages.home.content.properties.carousel_label'),
]); ?>

<?php Immobili::component('residenze/cards-grid', [
    'residenze' => $residenze,
    'presenter' => $presenter,
    'card' => 'card-base',
]); ?>
```

`cards-grid` usa la griglia responsive del modulo; `cards-swiper` abilita la dipendenza Swiper e
applica i breakpoint mobile-first standard: 1,05 card, 2 card da 769 px, 3 card da 993 px.
`id`, `slide_class` e `aria_label` valgono solo per lo swiper e sono ignorati dalla griglia.
In entrambi i casi non viene prodotto markup quando `immobili` o `residenze` è vuoto: titoli,
messaggi di lista vuota e paginazione restano responsabilità della pagina.

## Galleria della scheda immobile

La pagina di dettaglio (`view/pages/frontend/immobili/detail.php`) usa le funzioni del framework
**`__swiper()`** (carosello con thumbnails + lightbox) per le foto e **`__gallery()`** (griglia +
lightbox Fancybox) per le planimetrie. La pagina abilita i relativi bundle con
`Dependencies::swiper()` e `Dependencies::fancyapps()`.

Queste funzioni ricostruiscono le varianti responsive con `Image::src()`, quindi funzionano **solo
con immagini già processate** (varianti locali generate dall'ImageProcessor o dall'upload manuale).
Finché un immobile ha immagini non ancora processate (solo `source_url` remota), la pagina ricade sul
componente `gallery` (griglia statica) — nessuna immagine rotta. Dopo il cron immagini
(`/api/immobili/images/`) compare automaticamente il carosello.

> Per vedere il carosello sugli immobili di **seed** (immagini `picsum` remote), esegui prima
> l'endpoint immagini così vengono scaricate e processate in locale.

## Stili

Lo stile usa le **classi utility di `wonder-image/lib`** (griglia, spaziature, aspect box,
tipografia) più i token del sito e Bootstrap. L'unica eccezione sono gli asset della mappa
(sotto). Personalizza l'aspetto sovrascrivendo le view/componenti (sopra) o agendo sui token
del sito.

## Asset del modulo (css/js della mappa)

Il componente `map` carica due asset propri del modulo da `resources/assets/`:

- `css/immobili-map.css` — stile dei marker (scopato su `.immobili-map`);
- `js/immobili-map.js` — `initMap()`: caricamento dinamico della Google Maps JS API e
  marker `AdvancedMarkerElement` dal GeoJSON.

Le view li risolvono con `Immobili::asset('css/immobili-map.css')` (wrapper di
`module_asset()`, framework ≥ 2.2): di default vengono serviti **direttamente dal
modulo** (`vendor/wonder-image/immobili/resources/assets/...`), senza copie nel sito.

Per personalizzarli, pubblicali nel sito — la copia pubblicata ha sempre priorità:

```bash
php forge publish:module immobili --assets           # copia in assets/{ASSETS_VERSION}/
php forge publish:module immobili --assets --force   # ri-pubblica dopo un update del modulo
```

> Attenzione: le copie pubblicate non si aggiornano da sole con gli update del modulo.

La mappa richiede la chiave **`google_maps_api_key`** nel config del modulo — vedi
[Impostazioni](../configurazione/impostazioni.md#google-maps).

## Dati per le view

Usa `ImmobilePresenter` per ottenere un immobile arricchito e `ImmobileQuery` per liste filtrate,
così le tue view personalizzate riusano la stessa logica del modulo.

Nel dettaglio, i media sono esposti come collezioni semplici:

```php
$immobile->images;          // ['img1.jpg', 'img2.jpg', ...]
$immobile->imagesAlt;       // ['img1.jpg' => 'Titolo', ...]
$immobile->image;           // prima immagine, oppure ''
$immobile->planimetrie;     // ['planimetria1.jpg', ...]
$immobile->planimetrieAlt;  // ['planimetria1.jpg' => 'Titolo', ...]
```

Le liste rispettano il campo `position` di `immobili_immagini`; il valore delle mappe `*Alt`
proviene dal campo `titolo` ed è utilizzabile anche come caption.
