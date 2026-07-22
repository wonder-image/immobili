# Immobili manuali e seed

## Immobili creati a mano

Oltre agli immobili importati dai feed, è possibile **creare immobili direttamente dal sito**, dal
backend **Immobili → Immobili → Aggiungi immobile**.

- Gli immobili manuali hanno `provider = 'manual'` e `feed_source_id = 0`.
- Il form permette di inserire dati, **immagini** (upload con webp/resize automatici) e **descrizioni
  it/en**.
- La sincronizzazione dei feed **non tocca** gli immobili manuali (rimuove solo quelli del feed di
  origine).
- Per gli immobili **da feed**, invece, il backend consente solo la modifica dei flag manuali
  (visibile / in evidenza / venduto): i dati restano governati dal gestionale.

## Seed per la verifica locale

Per provare frontend e backend senza collegare un feed reale, è disponibile un **seed** di immobili
di esempio (con immagini placeholder da `picsum.photos` e descrizioni it/en).

Prerequisito: le tabelle del modulo devono esistere (una volta: `php forge update --local`).
In ambiente locale il seed **non richiede token**:

```bash
# 12 immobili di esempio (default)
curl "https://TUOSITO.test/api/immobili/seed/"

# numero personalizzato (1–100)
curl "https://TUOSITO.test/api/immobili/seed/?count=20"
```

Puoi anche aprire l'URL nel browser. Poi visita `https://TUOSITO.test/immobili/`.

- Disponibile **solo in ambiente locale** (host `localhost`, `.test`, `.local`, `.ddev.site`, …) o,
  fuori dal locale, fornendo il token dell'utente API `@immobili` (header Bearer o `?token=`).
- Rigenera il set a ogni chiamata (rimuove i seed precedenti).
- Gli immobili di seed hanno `provider = 'seed'` e non interferiscono con i feed.

Le immagini si vedono **subito**: il presenter usa le varianti webp solo se l'immagine è già stata
processata (`resized`), altrimenti ricade sulla `source_url`. Non serve quindi lanciare
`/api/immobili/images/` per vedere il seed — quell'endpoint è utile solo se vuoi convertire le
placeholder in webp locali (e richiede il token API).

> **Cosa prova il seed (e cosa no).** Il seed verifica **frontend, filtri, mappa, scheda, PDF e
> backend**, ma **non** esercita il codice di import (`GetrixProvider`/`GestimProvider`, tassonomie,
> upsert idempotente, autenticazione dei cron). Per validare una **sincronizzazione end-to-end** —
> parsing del feed, token dell'utente API `@immobili`, cron in pull/push — crea invece un
> `FeedSource` reale (anche di un cliente di prova) e premi **Sincronizza ora**. Vedi
> [API e sincronizzazione](api-e-sync.md).
