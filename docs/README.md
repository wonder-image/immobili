# Wonder Immobili

Modulo immobiliare per progetti **wonder-image/new-site**. Collega **uno o più feed**
provenienti da gestionali diversi (**Getrix**, **Gestim** e altri tramite l'interfaccia
`FeedProvider`), importa gli immobili in un modello dati unico e li pubblica con view e componenti
pronti, **bilingua (it/en)**.

## Cosa fa

- **Multi-feed / multi-gestionale**: N feed per sito, ognuno con provider, credenziali e opzioni,
  gestiti dal backend.
- **Modello dati unico**: tutti gli immobili confluiscono nelle tabelle `immobili*`, distinti da
  `provider` e dal feed di origine.
- **Frontend pronto**: lista con mappa e filtri, scheda dettaglio, immobili venduti, scheda PDF.
- **Backend e automazione**: configurazione feed, sincronizzazione manuale, CLI/cron e gestione di
  visibilità/evidenza/venduto.
- **Bilingua**: interfaccia e slug in italiano e inglese; descrizioni multilingua importate dal feed.
- **Estendibile**: nuovi gestionali via `FeedProvider`; view personalizzabili dal sito.

## Da dove iniziare

1. [Installazione](getting-started/installazione.md)
2. [Struttura del modulo](getting-started/struttura-modulo.md)
3. [Collegare uno o più feed](configurazione/feed-e-gestionali.md)
4. [Automatizzare con CLI o API](riferimento/api-e-sync.md#creare-i-cron)

## Mappa della documentazione

- **Guida introduttiva** — installazione e struttura.
- **Configurazione** — feed e gestionali, impostazioni, permessi.
- **Provider** — Getrix, Gestim, come aggiungerne di nuovi.
- **Frontend** — route/flusso e personalizzazione delle view.
- **Riferimento** — modello dati, API/CLI/sync, traduzioni, backend, sviluppo e test.
