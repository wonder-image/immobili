# API, CLI e sincronizzazione

## Autenticazione: utente API dedicato

Gli endpoint di sincronizzazione **non** usano una variabile d'ambiente condivisa.
Al primo accesso al pannello **Immobili → Feed** il modulo crea automaticamente un
**utente API dedicato** `@immobili` (area `api`, authority `immobili_sync`) e ne
genera un **token** JWT, salvato in `api_users` come per l'utente di sistema del
framework.

Quel token è **l'unico segreto degli endpoint HTTP**. Lo trovi come testo secondario nel pannello
del feed (etichetta _Token API (Bearer)_). Lo scheduler del framework e la CLI locale non lo usano
perché non passano da una richiesta di rete.

Ai soli endpoint HTTP puoi presentarlo in due modi:

- **Header** (consigliato per le chiamate manuali o da integrazioni):
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

## CLI locale — esecuzione manuale

Il modulo installa un unico binario Composer, `immobili`, con due sottocomandi utili per lanciare a
mano o diagnosticare gli stessi lavori delle [pianificazioni](#pianificazioni). **Non** va aggiunto
ai cron del server: la frequenza la gestisce lo scheduler del framework. Il percorso fisico può
essere invocato da qualunque directory: il binario ricava la radice del sito dal proprio path e
avvia il bootstrap di Wonder prima di chiamare i servizi.

```bash
php /PERCORSO/SITO/vendor/wonder-image/immobili/bin/immobili list
```

| Comando | Comportamento |
|---|---|
| `sync --feed=<ID>` | Sincronizza un singolo feed; è la forma consigliata per Getrix. |
| `sync` | Sincronizza tutti i feed attivi pull; i feed push (Gestim) sono saltati perché li chiama il gestionale con `callback`. |
| `images --limit=30` | Elabora il lotto successivo di immagini; il limite ammesso è 1–200 e il default è 30. |
| `list` | Elenca i sottocomandi senza avviare una sincronizzazione. |
| `<comando> --help` | Mostra opzioni e sintassi del sottocomando. |

La CLI non richiede il token Bearer e non effettua richieste HTTP verso il sito. Scrive il risultato
JSON su standard output e termina con codice `0` quando il task riesce oppure `1` quando il bootstrap
non parte, una sincronizzazione fallisce o il lotto immagini contiene errori.

Dalla radice del sito Composer rende disponibile anche la scorciatoia
`php vendor/bin/immobili <comando>`; è equivalente.

## Cosa fa la sincronizzazione

Per ogni feed (`FeedSyncService::sync`):

1. Sincronizza le tassonomie del provider (per Gestim arrivano nello ZIP in push).
2. Marca gli immobili del feed come "non più presenti".
3. Legge il feed e fa **upsert idempotente** (reimporta solo se più recente), preservando i flag
   manuali `visible`/`evidence`/`sold`.
4. Aggiorna immagini e descrizioni, genera slug/URL/QR code.
5. Rimuove gli immobili spariti dal feed e registra `last_sync_status`.

## Pianificazioni

Sincronizzazione e resize immagini **non** si inseriscono a mano nei cron del server: il modulo le
registra nello **scheduler di wonder-image/app** (`Immobili::tasks()`, contratto `ModuleTasks`).
Basta il cron unico del framework, già configurato per il sito (vedi la documentazione
di wonder-image/app, *Cron job da codice*). Le pianificazioni iniziali vengono create da
`php forge update --local` o al primo tick dello scheduler e si gestiscono in
**Backend → Dev → Automazioni → Pianificazioni**.

| Attività | Chiave | Default | Parametri |
|---|---|---|---|
| Immobili: sincronizzazione dei feed | `immobili.sync` | attiva, ogni 30 minuti, timeout 900 s | `feed`: id del feed; vuoto = tutti i feed pull attivi |
| Immobili: ridimensionamento delle immagini | `immobili.images` | attiva, ogni 5 minuti, timeout 300 s | `limit`: lotto da 1 a 200 (default 30) |

- I feed **push** (Gestim) sono saltati dalla sincronizzazione pianificata: li aggiorna il
  gestionale chiamando l'endpoint (vedi sotto). Una pianificazione con `feed` impostato su un feed
  push fallisce con un messaggio esplicito.
- Per frequenze diverse per feed aggiungi dal backend altre pianificazioni di `immobili.sync`, una
  per `feed`, e sospendi quella di default.
- Frequenza, stato e parametri definiti nel codice valgono **solo come valori iniziali**: dopo la
  creazione si modificano dal backend. **Esegui ora** dalla lista avvia il lavoro al tick successivo.
- Una sincronizzazione con errori o un lotto con immagini fallite viene registrato come esecuzione
  fallita nel registro dello scheduler, oltre che in **Immobili → Storico sync**.

### Gestim — push, nessuna pianificazione di sync

È Gestim a chiamare il tuo URL quando ci sono aggiornamenti. Nel pannello Gestim imposta come URL
di notifica (il token va in query perché Gestim non invia header):

```
https://TUOSITO/api/immobili/sync/?feed=<ID>&token=<TOKEN>
```

(Gestim vi aggiunge automaticamente `&callback=<url-zip>`.) Le immagini dei feed Gestim sono
elaborate dalla pianificazione `immobili.images`.

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
