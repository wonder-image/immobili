# Struttura del modulo

```
immobili/
├── module.json              Manifesto (slug, entrypoint, path, route, permessi)
├── composer.json            Pacchetto + autoload (Wonder\Plugin\Immobili\ → src/)
├── context.php              Stato condiviso (locale, feed attivi, filtri correnti)
├── config/
│   ├── module.php           Boot: registra lang + provider di default
│   ├── permissions.php      Permesso backend "immobili_manager"
│   └── routes/              route.frontend / route.backend / route.api
├── src/
│   ├── Immobili.php         Entrypoint ModuleInterface (view/component/layout/context)
│   ├── helpers.php          Funzioni globali immobili*()
│   ├── Models/              Immobile, ImmobileImmagine, ImmobileDescrizione, FeedSource, tassonomie
│   ├── Resources/           FeedSourceResource, ImmobileResource (backend CRUD auto)
│   ├── Feed/                FeedProvider, ProviderRegistry, GetrixProvider, GestimProvider, DTO
│   ├── Services/            FeedSyncService, ImageMirror, ImmobilePresenter, ImmobileQuery, PdfSheet
│   └── Support/             Taxonomy
├── http/
│   ├── api/task/sync.php    Endpoint di sincronizzazione
│   ├── api/frontend/search.php   Ricerca JSON
│   ├── frontend/pdf.php     Scheda immobile stampabile
│   └── backend/feed/sync.php Trigger "Sincronizza ora"
├── view/                    layout, components (card/filters/gallery/map/features), pages
├── lang/it · lang/en        Traduzioni (pages/components/urls)
├── resources/assets/        CSS del modulo
└── docs/                    Questa documentazione
```

## Concetti chiave

- **Provider** (`Feed/`): un adapter per gestionale (Getrix, Gestim, …) che normalizza il feed nel
  modello canonico. Registrato nel `ProviderRegistry`.
- **FeedSource** (`Models/FeedSource`): una riga di configurazione per ogni feed collegato al sito.
- **FeedSyncService**: orchestrazione dell'import (upsert idempotente, immagini, descrizioni, pulizia).
- **ImmobilePresenter**: arricchisce le righe per le view (prezzo/indirizzo formattati, etichette,
  immagini, descrizione nella lingua corrente, GeoJSON).

Vedi anche [Modello immobile](../riferimento/modello-immobile.md).
