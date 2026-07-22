# Backend

Il modulo aggiunge la sezione **Immobili** al backend, con due Resource.

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
- **Immobili da feed**: modificabili solo nei flag manuali (`visible`, `evidence`, `sold`); il resto è
  governato dal gestionale.
- **Immobili manuali** (**Aggiungi immobile**): creazione/modifica completa con immagini (upload con
  webp/resize automatici) e descrizioni it/en. La sync non li tocca. Vedi
  [Immobili manuali e seed](manuali-e-seed.md).

## Storico sincronizzazioni (`SyncLogResource`)

Sola lettura: elenca i file/sorgenti importati, i conteggi e gli esiti di ogni sincronizzazione e del
secondo piano immagini.

## Impostazioni PDF (`SettingsResource`)

Schermata singleton (una sola riga) con la configurazione della scheda PDF **comune a tutti i feed**:
logo, colori, e font/font_bold scelti dall'array `$FONT_FPDF` di wonder-image/app. Vedi
[Impostazioni](../configurazione/impostazioni.md).

## Rotte CRUD

Le rotte backend sono generate automaticamente dal `ResourceRouteRegistrar` a partire dalle Resource
del modulo: non serve dichiararle a mano.
