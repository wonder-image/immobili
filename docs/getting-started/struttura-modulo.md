# Struttura del modulo

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

## Concetti chiave

- **Provider** (`Feed/`): un adapter per gestionale (Getrix, Gestim, …) che normalizza il feed nel
  modello canonico. Registrato nel `ProviderRegistry`.
- **FeedSource** (`Models/System/FeedSource`): una riga di configurazione per ogni feed collegato al sito.
- **FeedSyncService**: orchestrazione dell'import (upsert idempotente, immagini, descrizioni, pulizia).
- **Reparti**: il modulo gestisce due tipologie speculari — **immobili** (da feed o manuali)
  e **residenze** (cantieri, sempre manuali). La regola di collocazione è *radice =
  trasversale, sottocartella = reparto*, sia in `src/` sia in `view/`.
- **Presenter** (`Catalog/`): arricchiscono le righe per le view (prezzo/indirizzo formattati,
  etichette, immagini, descrizione nella lingua corrente, GeoJSON). `CardViewModel` appiattisce
  le differenze fra i due reparti nella forma comune consumata da `components/card.php`.

Vedi anche [Modello immobile](../riferimento/modello-immobile.md).
