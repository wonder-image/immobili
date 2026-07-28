# Immobili manuali e seed

## Immobili creati a mano

Oltre agli immobili importati dai feed, è possibile **creare immobili direttamente dal sito**, dal
backend **Immobili → Immobili → Aggiungi immobile**.

- Gli immobili manuali hanno `provider = 'manual'` e `feed_source_id = 0`.
- Il form permette di inserire tutti i dati commerciali e tecnici dell'immobile, con sezioni
  separate per **fotografie** e **planimetrie** (upload con webp/resize automatici e anteprima
  interna al drag&drop). Autore (`creator_type` / `creator_id`) e origine vengono impostati lato
  server; `customer_id` e `company_id` non sono richiesti.
- La sincronizzazione dei feed **non tocca** gli immobili manuali (rimuove solo quelli del feed di
  origine).
- Per gli immobili **da feed**, invece, il form aggiorna soltanto lo stato editoriale; le azioni
  rapide della tabella gestiscono visibilità ed evidenza. I dati restano governati dal gestionale.

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
- Non popolano le FK tassonomia (`comune_id`, `tipologia_id`, … restano `NULL`): usano i nomi
  denormalizzati (`comune_nome` / `tipologia_nome`), sufficienti per frontend, filtri e scheda.

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

## Migrazione allo schema canonico (reseed)

Il passaggio alle [tassonomie canoniche](modello-immobile.md#tassonomie-canoniche-provider-agnostiche)
con **FK intere** è un cambio di schema **distruttivo**: le colonne `*_id` dell'immobile passano da
VARCHAR (codice nativo) a `INT` con `FOREIGN KEY` verso l'id canonico.

⚠️ Le tabelle esistenti contengono dati vecchi (`*_id` = vecchio *codice*) **incompatibili** con le
nuove FK: se i dati restano, l'`ALTER` che aggiunge le FK fallisce con *Error 1452*. Vai quindi
**prima** a svuotare, **poi** a migrare:

1. **Svuota** le tabelle del modulo (FK checks off: alcune FK potrebbero già essere state create):

   ```sql
   SET FOREIGN_KEY_CHECKS = 0;
   TRUNCATE TABLE immobili;
   TRUNCATE TABLE immobili_immagini;
   TRUNCATE TABLE immobili_descrizioni;
   TRUNCATE TABLE immobili_categorie;
   TRUNCATE TABLE immobili_macrotipologie;
   TRUNCATE TABLE immobili_tipologie;
   TRUNCATE TABLE immobili_regioni;
   TRUNCATE TABLE immobili_province;
   TRUNCATE TABLE immobili_comuni;
   TRUNCATE TABLE immobili_quartieri;
   TRUNCATE TABLE immobili_quartieri_zone;
   SET FOREIGN_KEY_CHECKS = 1;
   ```

2. **Migra** lo schema: `php forge update --local`. Ora l'`ALTER` aggiunge le FK su tabelle **vuote**
   (validazione su zero righe → nessun 1452); le tabelle esistono già, quindi nessun problema di
   ordine di creazione.
3. **Ripopola**: **Sincronizza ora** un `FeedSource` (Getrix semina le tassonomie canoniche
   riempiendo `getrix_id`) e/o rilancia il seed di esempio.

> Se il framework (`wonder-image/app`) include la migrazione con `FOREIGN_KEY_CHECKS = 0` (vedi
> `class/Sql/CreateTable.php`), i passi 1–2 si fondono: `forge update` applica le FK senza validare i
> dati vecchi e senza vincoli d'ordine; resta solo da svuotare + risincronizzare per avere dati
> canonici puliti.
