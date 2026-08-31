# Wonder Immobili

Modulo immobiliare per progetti [`wonder-image/new-site`](https://github.com/wonder-image/new-site),
basato su [`wonder-image/app`](https://github.com/wonder-image/app).

Permette a ogni sito di **collegare uno o più feed immobiliari** provenienti da gestionali diversi
(**Getrix**, **Gestim**, e altri tramite l'interfaccia `FeedProvider`), importarli in un modello dati
unico e pubblicarli con view e componenti pronti, **bilingua (it/en)**.

## Caratteristiche

- **Multi-feed / multi-gestionale**: N feed per sito, ognuno con il proprio provider e credenziali,
  gestiti dal backend.
- **Modello dati canonico**: gli immobili di ogni gestionale confluiscono nelle stesse tabelle
  `immobili*`, distinti dal campo `creator_type` e dal feed di origine (`feed_source_id`).
- **Frontend pronto**: lista con mappa e filtri, scheda dettaglio, immobili venduti, esportazione PDF.
- **Backend**: configurazione dei feed, sincronizzazione manuale/pianificata, gestione visibilità.
- **Bilingua**: interfaccia e slug in italiano e inglese; descrizioni multilingua dal feed.
- **Estendibile**: aggiungi nuovi gestionali implementando `FeedProvider`; personalizza le view dal
  sito senza toccare il modulo.

## Installazione rapida

```bash
composer require wonder-image/immobili
```

Abilita il modulo in `custom/config/modules.php`:

```php
return [
    'immobili' => [
        'enabled' => true,
    ],
];
```

Crea le tabelle:

```bash
php forge update --local
```

Poi apri il backend, aggiungi un feed (provider + credenziali) e premi **Sincronizza ora**.

Per i cron esiste una CLI che richiama direttamente gli stessi servizi degli endpoint HTTP e può
essere eseguita tramite il path assoluto del file reale, senza link. I comandi canonici, inclusa
l'alternativa HTTP per scheduler esterni, sono mantenuti in un solo punto:
[`docs/riferimento/api-e-sync.md`](docs/riferimento/api-e-sync.md#creare-i-cron).

## Documentazione

La documentazione completa (in italiano) è nella cartella [`docs/`](docs/README.md).
