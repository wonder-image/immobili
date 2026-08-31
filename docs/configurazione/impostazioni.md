# Impostazioni

Le **opzioni di import** vivono sul singolo **feed** (`immobili_feed`), così ogni feed può avere
comportamenti diversi; le **impostazioni del modulo** (scheda PDF e scheda web) sono invece
**globali** e comuni a tutti i feed (vedi sotto).

## Opzioni di import

| Opzione                 | Effetto |
| ----------------------- | ------- |
| **Salva immagini in locale** | Se attiva, le immagini del feed vengono scaricate in `/upload/immobili/...`; altrimenti si usano gli URL remoti del gestionale. |
| **Visibili di default** | Stato `visible` dei nuovi immobili importati. |
| **In evidenza di default** | Stato `evidence` dei nuovi immobili. |
| **Venduti di default**  | Stato `sold` iniziale (per i gestionali che non lo forniscono). |

I flag `visible`, `evidence`, `sold` impostati a mano dal backend sono **preservati** alle
sincronizzazioni successive. Fa eccezione `sold` quando il gestionale lo fornisce esplicitamente
(es. Gestim), nel qual caso il feed è autoritativo.

## Impostazioni del modulo (globali)

La configurazione **comune a tutti i feed** vive in una **schermata unica**
(**Immobili → Impostazioni**), risorsa singleton (`immobili_settings`, una sola riga). È la
**centrale di controllo** del modulo, divisa in due card.

### Scheda PDF

- **Logo PDF**: selezione tra le varianti configurate in **Media → Logo**
  (Logo, Logo nero, Logo bianco, Icona, Icona nera, Icona bianca).
- **Colore primario** / **Colore secondario**.
- **Font** e **Font (grassetto)**: selezionabili dall'**elenco completo** dei font FPDF del
  framework, esposto da `Wonder\App\Support\FpdfFonts` (Arial, Times, Courier, Montserrat in tutti
  i pesi, EB Garamond, Nunito Sans, American Typewriter, …).
- **Dati mostrati sul PDF**: elenco **ordinabile** degli attributi da stampare nella scheda
  (riferimento, zona, contratto, prezzo, superficie, locali, classe energetica, …). Se vuoto si
  usano i default del codice / override del sito (`PdfConfig`).

### Scheda immobile

- **Dati mostrati nella scheda**: elenco **ordinabile** degli attributi mostrati nella scheda web
  del dettaglio (componente `features`). Se vuoto si usano i default del catalogo.

Le due liste pescano dal catalogo condiviso `Support\AttributeCatalog` (unica fonte di chiavi,
etichette e default). La scheda PDF stampabile dell'immobile è raggiungibile su
`/immobili/{slug}/pdf/`.

## Google Maps

La mappa del frontend (componente `map`, usato da lista e dettaglio) richiede una chiave
**Google Maps JS API**. Si imposta nel config del modulo, che il sito sovrascrive in
`custom/config/modules/immobili.php`:

```php
<?php

return [
    'google_maps_api_key' => 'LA-TUA-CHIAVE',
    // Facoltativo: Map ID vettoriale dalla console Google Cloud.
    // Senza, il js usa DEMO_MAP_ID (ok in sviluppo, non in produzione).
    'google_maps_map_id' => '',
];
```

Senza chiave la mappa non viene inizializzata (warning in console, contenitore vuoto):
il resto della pagina funziona normalmente.

## Token di sincronizzazione

Gli endpoint HTTP di sync **non** usano variabili d'ambiente: il modulo crea automaticamente un
**utente API dedicato** (`@immobili`) con un token, generato al primo accesso al pannello
**Immobili → Feed**. È quel token a proteggere le chiamate (header `Authorization: Bearer …`
o, per Gestim in push, `?token=…`). La CLI locale non passa dagli endpoint e non usa il token.
Dettagli e cron in
[API, CLI e sincronizzazione](../riferimento/api-e-sync.md).
