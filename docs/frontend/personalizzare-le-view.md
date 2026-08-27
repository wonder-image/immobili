# Personalizzare le view

Tutte le view e i componenti del modulo sono **sovrascrivibili dal sito** senza toccare il pacchetto.

## Override di un componente o di una pagina

Crea lo stesso file sotto `custom/modules/immobili/view/` nel sito. Ha priorità su quello del modulo.

Esempi:

```
custom/modules/immobili/view/components/card.php                    → sostituisce la card
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
| `card` | `['item' => CardViewModel]` |
| `cards` | `['items' => CardViewModel[], 'layout' => 'grid'\|'swiper', 'class' => 'mt-4', 'id' => '…', 'slide_class' => '…', 'aria_label' => '…']` |
| `specs` | `['immobile' => $immobile]` — coppie attributo → valore |
| `amenities` | `['features' => ['ascensore', 'giardino', …]]` — icona + etichetta |
| `map` | `['features' => $geojson, 'zoom' => 15, 'mapId' => 'id-opzionale']` |
| `energy-class/badge` · `line` · `scale` | `['immobile' => $immobile]` oppure `['scale' => EnergyScale]` |
| `immobili/filters` | `['filters' => [...], 'action' => $url]` |
| `residenze/timeline` | `['inizio' => '03/2025', 'fine' => '2026', 'stato' => '…']` |

Richiamali con `Immobili::component('card', ['item' => $item])`.

### Card e collezioni

`card` è **una sola** per i due reparti: riceve un `CardViewModel`, che appiattisce le
differenze fra immobile e residenza in slot opzionali (`highlight` per il prezzo,
`excerpt` per la descrizione breve, `meta` per la riga di icone). Dentro il componente non
c'è nessun ramo per tipo.

Il view-model si costruisce dal presenter del reparto:

```php
use Wonder\Plugin\Immobili\Catalog\CardViewModel;

// Immobili: da ImmobileQuery::cards()
$items = CardViewModel::fromImmobili($query->cards($rows));

// Residenze: dalle righe DB + ResidenzaPresenter
$items = CardViewModel::fromResidenze($rows, $presenter);
```

`cards` rende la collezione nei due layout:

```php
<?php Immobili::component('cards', [
    'items' => $items,
    'class' => 'mt-4',
]); ?>

<?php Immobili::component('cards', [
    'items' => $items,
    'layout' => 'swiper',
    'id' => 'immobili-in-evidenza',
    'class' => 'mt-6',
    'aria_label' => __t('pages.home.content.properties.carousel_label'),
]); ?>
```

Il layout `grid` riusa la griglia responsive del modulo; `swiper` abilita la dipendenza Swiper e
applica i breakpoint mobile-first standard: 1,05 card, 2 card da 769 px, 3 card da 993 px.
`id`, `slide_class` e `aria_label` valgono solo per lo swiper e sono ignorati dalla griglia.
In entrambi i casi non viene prodotto markup quando `items` è vuoto: titoli, messaggi di lista
vuota e paginazione restano responsabilità della pagina.

Il prefisso `immobili-` non è necessario nei nomi: il namespace è già espresso dalla chiamata
`Immobili::component(...)`.

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
