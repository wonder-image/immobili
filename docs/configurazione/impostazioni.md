# Impostazioni

Le impostazioni vivono sul singolo **feed** (`immobili_feed`), non a livello globale: così ogni feed
può avere comportamenti diversi.

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

## Impostazioni PDF (globali)

La scheda PDF è **comune a tutti i feed**, quindi la sua configurazione vive in una **schermata unica**
(**Immobili → Impostazioni PDF**), non sul singolo feed:

- **Logo PDF**
- **Colore primario** / **Colore secondario**
- **Font** e **Font (grassetto)**: selezionabili dall'elenco dei font FPDF del framework
  (`$FONT_FPDF` di wonder-image/app: Arial, Times, Montserrat, EB Garamond, …).

È una risorsa singleton (`immobili_settings`, una sola riga). La scheda stampabile dell'immobile è
raggiungibile su `/immobili/{slug}/pdf/`.

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

Gli endpoint di sync **non** usano variabili d'ambiente: il modulo crea automaticamente un
**utente API dedicato** (`@immobili`) con un token, generato al primo accesso al pannello
**Immobili → Feed**. È quel token a proteggere le chiamate (header `Authorization: Bearer …`
o, per Gestim in push, `?token=…`). Dettagli e cron in
[API e sincronizzazione](../riferimento/api-e-sync.md).
