# Backend

Il modulo aggiunge la sezione **Immobili** al backend (Feed, Immobili, Storico sync, Impostazioni).

## Feed (`FeedSourceResource`)

- CRUD dei feed collegati (uno o più per sito).
- Campi: nome, gestionale, attivo, credenziali, opzioni di import, impostazioni PDF.
- **Sincronizza ora**: rotta backend `/backend/immobili-feed/{id}/sync/` che esegue subito la
  sincronizzazione del feed e torna alla lista. La colonna *Ultima sincronizzazione* mostra l'esito.

## Immobili (`ImmobileResource`)

- Elenco di tutti gli immobili (da feed **e** manuali).
- Toggle rapidi in tabella per **In evidenza**, **Visibile**, **Venduto**.
- Filtri per contratto e stato; ricerca su riferimento/indirizzo/quartiere/zona.
- **Aggiorna immobili**: forza dalla testata della lista la sincronizzazione di tutti i feed attivi.
- **Immobili da feed**: dal form si aggiorna lo stato editoriale, tradotto nei flag `visible` e
  `sold`; `evidence` resta disponibile come azione rapida in tabella. Gli altri dati sono governati
  dal gestionale.
- **Immobili manuali** (**Aggiungi immobile**): form completo organizzato in colonna principale e
  sidebar, con tipologia, posizione, contratto/costi, composizione, accessori, caratteristiche,
  energia, stato, più video YouTube, più Virtual Tour, fotografie e planimetrie (upload con
  webp/resize automatici). Fotografie e planimetrie hanno sezioni e ordinamenti separati; in modifica
  le anteprime sono mostrate dentro il relativo drag&drop, in una griglia responsive fino a quattro
  elementi per riga. `customer_id` e `company_id` non fanno parte del modello. La sync non tocca gli
  immobili manuali. Vedi
  [Immobili manuali e seed](manuali-e-seed.md).

## Storico sincronizzazioni (`SyncLogResource`)

Sola lettura, **righe non eliminabili** (registro storico): elenca sorgente/file importato, i
conteggi di immobili e immagini e l'**Esito** come *badge* (Successo/Errore) di ogni run di
sincronizzazione e del secondo piano immagini. L'azione **Scarica** per riga genera un report JSON
del run (orari, conteggi, esito, problematiche e riferimento all'artifact archiviato).

## Impostazioni (`SettingsResource`)

Schermata singleton (una sola riga), **centrale di controllo** del modulo: card *Scheda PDF* (logo,
colori, font dall'elenco completo `Wonder\App\Support\FpdfFonts`, e i **dati mostrati sul PDF** come
repeater ordinabile) e card *Scheda immobile* (**dati mostrati nella scheda** web). Le liste di
attributi pescano dal catalogo condiviso `Support\AttributeCatalog`. Vedi
[Impostazioni](../configurazione/impostazioni.md).

## Rotte CRUD

Le rotte backend sono generate automaticamente dal `ResourceRouteRegistrar` a partire dalle Resource
del modulo: non serve dichiararle a mano.
