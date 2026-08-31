# API, CLI e sincronizzazione

## Autenticazione: utente API dedicato

Gli endpoint di sincronizzazione **non** usano una variabile d'ambiente condivisa.
Al primo accesso al pannello **Immobili → Feed** il modulo crea automaticamente un
**utente API dedicato** `@immobili` (area `api`, authority `immobili_sync`) e ne
genera un **token** JWT, salvato in `api_users` come per l'utente di sistema del
framework.

Quel token è **l'unico segreto degli endpoint HTTP**. Lo trovi come testo secondario nel pannello
del feed (etichetta _Token API (Bearer)_). La CLI locale non usa il token perché non espone una
richiesta di rete.

Ai soli endpoint HTTP puoi presentarlo in due modi:

- **Header** (consigliato per gli scheduler HTTP):
  `Authorization: Bearer <TOKEN>`
- **Query string** (usata da Gestim in push, che non può impostare header):
  `?token=<TOKEN>` — l'endpoint lo trasforma internamente in Bearer.

## Endpoint di sincronizzazione

```
GET /api/immobili/sync/                            → sincronizza tutti i feed attivi
GET /api/immobili/sync/?feed=<id>                  → sincronizza solo quel feed
GET /api/immobili/sync/?feed=<id>&callback=<zip>   → push Gestim
```

Risposta JSON:

```json
{ "success": true, "status": 200, "response": [ { "success": true, "count": 42, "feed": 1 } ] }
```

## CLI locale

Il modulo installa un unico binario Composer, `immobili`, con due sottocomandi. Il percorso fisico
può essere invocato da qualunque directory: il binario ricava la radice del sito dal proprio path e
avvia il bootstrap di Wonder prima di chiamare i servizi.

```bash
php /PERCORSO/SITO/vendor/wonder-image/immobili/bin/immobili list
```

Il path fisico è la forma consigliata nei cron: non dipende dalla directory corrente e non usa il
link `vendor/bin/immobili`. Se il path contiene spazi, racchiudilo tra virgolette.

| Comando | Comportamento |
|---|---|
| `sync --feed=<ID>` | Sincronizza un singolo feed; è la forma consigliata per Getrix. |
| `sync` | Sincronizza tutti i feed attivi. Usalo soltanto se sono tutti provider pull: Gestim è push e deve essere chiamato dal gestionale con `callback`. |
| `images --limit=30` | Elabora il lotto successivo di immagini; il limite ammesso è 1–200 e il default è 30. |
| `list` | Elenca i sottocomandi senza avviare una sincronizzazione. |
| `<comando> --help` | Mostra opzioni e sintassi del sottocomando. |

La CLI non richiede il token Bearer e non effettua richieste HTTP verso il sito. Scrive il risultato
JSON su standard output e termina con codice `0` quando il task riesce oppure `1` quando il bootstrap
non parte, una sincronizzazione fallisce o il lotto immagini contiene errori. Prima di aggiungere le
redirezioni del cron, esegui entrambi i comandi manualmente e controlla il JSON restituito.

Dalla radice del sito Composer rende disponibile anche la scorciatoia
`php vendor/bin/immobili <comando>`; è equivalente, ma non è necessaria per l'esecuzione tramite path
assoluto.

## Cosa fa la sincronizzazione

Per ogni feed (`FeedSyncService::sync`):

1. Sincronizza le tassonomie del provider (per Gestim arrivano nello ZIP in push).
2. Marca gli immobili del feed come "non più presenti".
3. Legge il feed e fa **upsert idempotente** (reimporta solo se più recente), preservando i flag
   manuali `visible`/`evidence`/`sold`.
4. Aggiorna immagini e descrizioni, genera slug/URL/QR code.
5. Rimuove gli immobili spariti dal feed e registra `last_sync_status`.

## Creare i cron

**Sincronizzazione** e **resize immagini** vanno su **due cron separati**. Per ciascun task scegli
una sola modalità:

- **CLI locale**, consigliata quando il cron gira sullo stesso server del sito: non passa da HTTP e
  non richiede token;
- **HTTP**, per scheduler esterni che possono chiamare soltanto un URL.

Non configurare entrambe le modalità per lo stesso task, altrimenti la stessa lavorazione partirebbe
due volte.

### CLI locale — consigliata

Sostituisci `/PERCORSO/SITO` con la radice assoluta del sito e `<ID>` con l'id del feed Getrix.

```cron
# Getrix: sincronizzazione del singolo feed ogni 30 minuti
*/30 * * * * /usr/bin/php /PERCORSO/SITO/vendor/wonder-image/immobili/bin/immobili sync --feed=<ID> > /dev/null 2>&1

# Getrix e Gestim: elaborazione immagini ogni 5 minuti, lotto da 30
*/5 * * * * /usr/bin/php /PERCORSO/SITO/vendor/wonder-image/immobili/bin/immobili images --limit=30 > /dev/null 2>&1
```

Durante la prima configurazione rimuovi temporaneamente `> /dev/null 2>&1`: in questo modo vedi il
JSON e gli eventuali errori. Riattiva la redirezione solo dopo una prova manuale riuscita.

### HTTP — scheduler esterno

Sostituisci `TUOSITO`, `<TOKEN>` (token dell'utente `@immobili`) e `<ID>` (id del feed).

#### Getrix — pull

Getrix espone un file da scaricare: sei tu a doverlo leggere periodicamente.

```cron
*/30 * * * * curl -fsS -H "Authorization: Bearer <TOKEN>" "https://TUOSITO/api/immobili/sync/?feed=<ID>" > /dev/null
```

#### Immagini — Getrix e Gestim

```cron
*/5 * * * * curl -fsS -H "Authorization: Bearer <TOKEN>" "https://TUOSITO/api/immobili/images/" > /dev/null
```

### Gestim — push, nessun cron di sincronizzazione

È Gestim a chiamare il tuo URL quando ci sono aggiornamenti: **non** serve un cron di sync.
Nel pannello Gestim imposta come URL di notifica (il token va in query perché Gestim non
invia header):

```
https://TUOSITO/api/immobili/sync/?feed=<ID>&token=<TOKEN>
```

(Gestim vi aggiunge automaticamente `&callback=<url-zip>`.)

## Ricerca (JSON)

```
GET /api/immobili/search/?comune=&contratto=V&prezzo_min=100000&page=1
```

Restituisce card sintetiche + GeoJSON + paginazione. La lista frontend funziona comunque server-side
via form GET; questo endpoint serve per integrazioni AJAX/esterne.

## Altri endpoint

```
GET /api/immobili/images/           → secondo piano immagini (webp/resize a lotti)
GET /api/immobili/seed/             → seed di esempio (solo locale) — vedi Immobili manuali e seed
GET /api/immobili/reindex/          → backfill dei campi derivati di ricerca (locale, oppure token)
GET /api/immobili/search/           → ricerca immobili in JSON
GET /immobili/idealista/            → feed XML per il portale Idealista (export)
```

## Storico

Ogni run di sincronizzazione e del secondo piano immagini viene registrato nella tabella
`immobili_sync_log` (file/sorgente importato, conteggi, esito) e consultabile dal backend in
**Immobili → Storico sync**.

Per Getrix la colonna **Sorgente / file** contiene il path relativo dello ZIP archiviato. Nella stessa
cartella vengono conservati anche l'XML estratto e `metadata.json`, così ogni risposta del gestionale
può essere verificata a posteriori. Gli archivi sono dati runtime e vivono sotto
`storage/immobili/feed-sync/getrix/`.
