# Provider Getrix

Getrix pubblica un feed XML (zippato) scaricabile in **pull** dato un account ID. Il modulo riusa
l'SDK del framework `Wonder\Plugin\Getrix\Import` (endpoint `feed.getrix.it`).

## Configurazione del feed

| Campo       | Valore                          |
| ----------- | ------------------------------- |
| Gestionale  | Getrix                          |
| Codice / ID | il tuo **Getrix ID** (account)  |
| Attivo      | Sì                              |

Salva e premi **Sincronizza ora**.

## Cosa importa

- **Immobili**: da `feed.getrix.it/xml/{id}.zip`.
- **Tassonomie**: categorie, macrotipologie, tipologie, regioni, province, comuni, quartieri e zone
  (endpoint `tipologie.asp`, `comuni.asp`, `quartieri.asp`). Getrix **semina le tabelle canoniche**
  (upsert per chiave naturale) riempiendo la colonna mappa `getrix_id`.
- **Immagini**: varianti `xs/m/xl/xxxl`; in locale se "Salva immagini" è attivo, altrimenti via URL.
- **Descrizioni**: multilingua (IT/EN/…), una riga per lingua.

## Note

- I codici nativi Getrix (categoria, tipologia, comune) vengono risolti all'**id canonico** (via la
  colonna mappa `getrix_id`) e salvati nelle FK `*_id` dell'immobile; le etichette si leggono per id
  dalle tassonomie canoniche.
- La sincronizzazione è idempotente: un immobile viene reimportato solo se la sua `DataModifica` è più
  recente di quella salvata.
- Ogni esecuzione conserva lo ZIP originale, l'XML estratto e un file `metadata.json` (timestamp,
  dimensioni e SHA-256) sotto
  `storage/immobili/feed-sync/getrix/feed-{id}/AAAA/MM/GG/{timestamp}-{id-casuale}/`. Il path dello ZIP
  è riportato anche in **Immobili → Storico sync**.
- Un download/XML non valido o un feed senza immobili importabili produce un errore e non modifica né
  rimuove gli immobili già presenti.
- Le tassonomie globali Getrix già presenti vengono riutilizzate, evitando di ricostruire migliaia di
  comuni a ogni sync. Per forzarne l'aggiornamento, richiama l'endpoint con
  `&refresh_taxonomies=1` (operazione più lenta).

## Pianificazioni (Getrix = pull)

Getrix è **pull**: il file va scaricato periodicamente. Non serve configurare cron: le attività
`immobili.sync` (ogni 30 minuti) e `immobili.images` (ogni 5 minuti) sono registrate dal modulo
nello scheduler del framework e si gestiscono in **Backend → Dev → Automazioni → Pianificazioni**.
Dettagli in [Pianificazioni](../riferimento/api-e-sync.md#pianificazioni). Vedi anche
[Feed e gestionali](../configurazione/feed-e-gestionali.md).
