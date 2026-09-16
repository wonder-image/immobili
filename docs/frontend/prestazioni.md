# Prestazioni della scheda immobile

La scheda standard carica video, tour virtuali e mappe solo dopo l'attivazione del pulsante. I contenuti rimangono in un `<template>` inerte fino al clic; senza JavaScript il pulsante rimane un collegamento alla risorsa esterna. I titoli degli iframe sono tradotti e includono il nome dell'immobile.

## Componenti

- Gli iframe usano `Iframe::deferred(button: Button::make($label))`, senza wrapper PHP o JavaScript specifici del modulo. Rapporto predefinito 16:9; nessuna altezza obbligatoria.
- Le mappe usano `Wonder\Elements\Media\Deferred`. Il comportamento condiviso `DeferredContent` appartiene a wonder-image/lib. `interactive => true` mantiene la mappa immediata; `fill => true` riempie un genitore posizionato e dimensionato. La scheda usa il rapporto del contenitore, anche quando cambia tra desktop e mobile.
- Swiper usa direttamente `priority()->imageSizes(...)->thumbsImageSizes(...)` del framework. Gallery ricava le dimensioni responsive dalle colonne; `imageSizes(...)` permette di personalizzarle. Il wrapper `media/property-gallery` è stato eliminato.

Le immagini remote rimangono remote. Le varianti responsive si applicano alle immagini del sito già elaborate dal normale processo immagini; il componente non scarica o converte foto durante la richiesta.

## Aggiornare siti esistenti

Aggiornare wonder-image/app e i bundle wonder-image/lib insieme al modulo. I siti con override devono allineare `pages/frontend/immobili/detail.php` e `components/map.php`. Eliminare i vecchi `components/media/property-gallery.php`, `components/media/deferred.php` e `js/immobili-deferred-media.js`. Le traduzioni restano nel modulo/sito. I contratti sono in `wonder-image/app/docs/app/elementi/deferred-media.md` e `responsive-media.md`.

Gli override della lista possono riusare `$PAGINATION->max_row` senza ripetere `sqlCount()`. Rimuovere la query GeoJSON soltanto se la view non visualizza una mappa.

## Verifica

```sh
php tests/property-media.php
php tests/property-media.php /percorso/del/sito
```

I test non accedono al database o alla rete. Nel browser verificare inoltre slider, lightbox e caricamento su richiesta di video, tour e mappe, su mobile e desktop. Prima del clic non devono partire richieste a YouTube, Matterport e Google Maps attribuibili a questi componenti. Ripetere PageSpeed dopo il deploy; i risultati locali non sostituiscono il test del server pubblicato.
