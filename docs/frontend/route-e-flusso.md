# Route e flusso

## Rotte frontend

| Nome              | URL (default)              | Pagina        |
| ----------------- | -------------------------- | ------------- |
| `immobili.list`   | `/immobili/`               | lista + filtri + mappa |
| `immobili.sold`   | `/immobili/venduti/`       | immobili venduti |
| `immobili.detail` | `/immobili/{slug}/`        | dettaglio     |
| `immobili.pdf`    | `/immobili/{slug}/pdf/`    | scheda stampabile |

Gli slug localizzati sono definiti in `lang/{it,en}/urls.json` e risolti con `__r('immobili.list')`.

## Flusso della lista

1. `context.php` legge i filtri dalla query string (`comune`, `contratto`, `prezzo_min`, …).
2. `ImmobileQuery::search()` filtra gli immobili **visibili e non venduti**, ordina e pagina.
3. La pagina renderizza: componente `immobili/filters`, componente `map`, componente `cards`
   (che riusa `card`) e paginazione.

I filtri sono un semplice form **GET**: funzionano senza JavaScript. L'endpoint
`/api/immobili/search/` espone gli stessi risultati in JSON per usi AJAX/esterni.

## Flusso del dettaglio

1. Lo `slug` (`{slug}`) viene letto da `$ROUTE_PARAMETERS['slug']`.
2. Si carica l'immobile per `dir = slug` (solo se `visible`).
3. `ImmobilePresenter::present()` produce prezzo/indirizzo formattati, immagini, descrizione nella
   lingua corrente, GeoJSON per la mappa.
