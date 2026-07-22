# API e sincronizzazione

## Autenticazione: utente API dedicato

Gli endpoint di sincronizzazione **non** usano una variabile d'ambiente condivisa.
Al primo accesso al pannello **Immobili → Feed** il modulo crea automaticamente un
**utente API dedicato** `@immobili` (area `api`, authority `immobili_sync`) e ne
genera un **token** JWT, salvato in `api_users` come per l'utente di sistema del
framework.

Quel token è **l'unico segreto** necessario per la sincronizzazione. Lo trovi come
**testo secondario nel pannello del feed** (etichetta _Token API (Bearer)_): copialo
da lì per configurare i cron.

Puoi presentarlo in due modi:

- **Header** (consigliato, usato dai cron):
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

## Cosa fa la sincronizzazione

Per ogni feed (`FeedSyncService::sync`):

1. Sincronizza le tassonomie del provider (per Gestim arrivano nello ZIP in push).
2. Marca gli immobili del feed come "non più presenti".
3. Legge il feed e fa **upsert idempotente** (reimporta solo se più recente), preservando i flag
   manuali `visible`/`evidence`/`sold`.
4. Aggiorna immagini e descrizioni, genera slug/URL/QR code.
5. Rimuove gli immobili spariti dal feed e registra `last_sync_status`.

## Creare i cron

Sostituisci `TUOSITO`, `<TOKEN>` (il token dell'utente `@immobili`) e `<ID>` (id del feed).
**Sincronizzazione** e **resize immagini** vanno su **due cron separati**.

### Getrix — pull (serve un cron di sincronizzazione)

Getrix espone un file da scaricare: sei tu a doverlo leggere periodicamente.

```cron
# Sincronizzazione immobili (ogni 30 min)
*/30 * * * * curl -s -H "Authorization: Bearer <TOKEN>" "https://TUOSITO/api/immobili/sync/?feed=<ID>" > /dev/null
```

### Gestim — push (NIENTE cron di sincronizzazione)

È Gestim a chiamare il tuo URL quando ci sono aggiornamenti: **non** serve un cron di sync.
Nel pannello Gestim imposta come URL di notifica (il token va in query perché Gestim non
invia header):

```
https://TUOSITO/api/immobili/sync/?feed=<ID>&token=<TOKEN>
```

(Gestim vi aggiunge automaticamente `&callback=<url-zip>`.)

### Immagini — entrambi i provider (cron separato)

Secondo piano della pipeline: scarica gli originali e genera le varianti webp.

```cron
# Ridimensionamento immagini (ogni 5 min)
*/5 * * * * curl -s -H "Authorization: Bearer <TOKEN>" "https://TUOSITO/api/immobili/images/" > /dev/null
```

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
