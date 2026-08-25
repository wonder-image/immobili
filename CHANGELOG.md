# Changelog

Tutte le modifiche rilevanti a `wonder-image/immobili` sono documentate qui.
Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/).

## [Unreleased]

### Aggiunto
- Reparto **Residenze** (cantieri/costruzioni): Model `Residenza` + gallery
  `ResidenzaImmagine`, `ResidenzaResource` (CRUD backend), frontend
  `/residenze/` e `/residenze/{slug}/` con timeline, features, capitolato PDF,
  classe energetica, unità abitative, mappa e immobili collegati (FK
  `immobili.residenza_id`). Traduzioni it/en. Cover = prima immagine della
  gallery.

## [1.0.0] - non ancora rilasciato

### Tassonomie canoniche, centrale impostazioni e UI
- **Tassonomie provider-agnostiche**: `immobili_categorie/macrotipologie/tipologie/
  regioni/province/comuni/quartieri/quartieri_zone` diventano canoniche (una riga
  per entità reale) con chiave naturale nostra (`chiave`, `cod_catastale`, `sigla`)
  e colonne mappa `getrix_id` / `gestim_id` estendibili. **Getrix** semina il
  canonico via upsert (categorie mappate su chiavi nostre, geografia per
  `cod_catastale`); **Gestim** aggancia per nome (no auto-create).
- **FK intere**: le colonne relazione dell'immobile (`comune_id`, `categoria_id`,
  `macrotipologia_id`, `tipologia_id`, `quartiere_id`, `quartiere_zona_id`)
  diventano `INT` con `FOREIGN KEY … ON DELETE SET NULL` verso l'id canonico; gli
  enum di attributo (contratto, cucina, riscaldamento, …) restano VARCHAR.
- **`SettingsResource` centrale di controllo**: card *Scheda PDF* (logo, colori,
  font completi da `Wonder\App\Support\FpdfFonts`, dati mostrati) e card *Scheda
  immobile* (dati mostrati), con repeater ordinabili dal catalogo condiviso
  `AttributeCatalog` (usato anche da `PdfConfig`/`features.php`).
- **Storico sync** (`SyncLogResource`): colonna *Esito* come badge, righe non
  eliminabili, azione **Scarica** (report JSON con orari e problematiche).
- **UI**: risolto il select tagliato da `overflow:hidden` (lista aperta portata a
  `position:fixed` in `wonder-image/lib`, `form/select.js`); contenitore dropdown
  opzionale per i filtri (`wi-dropdown-box`, flag `dropdown` in `filters.php`).

### Nota di migrazione
Cambio schema distruttivo (VARCHAR → INT FK). Richiede **reseed**: drop delle
tabelle immobili + tassonomie e re-sync/seed. Da validare su un sito con DB e feed
reali (creazione FK, ordine tabelle, reseed).

### Aggiunto
- Struttura del modulo (entrypoint `Immobili`, routing frontend/backend/api, i18n it/en).
- Componenti di collezione `cards-grid` e `cards-swiper`, entrambi basati sulla stessa `card`
  e sugli oggetti preparati da `ImmobileQuery::cards()`.
- Modello dati canonico dell'immobile con tassonomie e tabella feed multi-sorgente.
- Astrazione `FeedProvider` con adapter completi per **Getrix** e **Gestim**.
- Backend: gestione dei feed (`FeedSourceResource`) e degli immobili importati
  (`ImmobileResource`) con sincronizzazione manuale e via endpoint task.
- Frontend: lista con mappa e filtri, dettaglio, venduti, scheda PDF; bilingua it/en.
- Asset frontend della mappa (`resources/assets/css/immobili-map.css`,
  `resources/assets/js/immobili-map.js`): marker Google Maps
  (`AdvancedMarkerElement`) dal GeoJSON con caricamento dinamico dell'API.
  Risolti nelle view con `Immobili::asset()` (`module_asset()` del framework
  ≥ 2.2, richiesto da `frameworkCompatibility`), personalizzabili dal sito
  con `php forge publish:module immobili --assets`.
- Config `google_maps_api_key` / `google_maps_map_id` (override del sito in
  `custom/config/modules/immobili.php`) letti da `Immobili::config()`.
- Documentazione in italiano (GitBook).
- Campi denormalizzati `comune_nome` / `tipologia_nome` / `ricerca` su `immobili`,
  popolati al sync, per rendere i filtri (comune/tipologia/ricerca libera)
  esprimibili in SQL.
- Task bearer `GET /api/immobili/reindex/` per il backfill idempotente dei campi
  derivati di ricerca sugli immobili già importati.

### Modificato
- Media dell'immobile normalizzati come array di URL: i quattro campi
  `youtube_1..4` confluiscono nel nuovo `youtube` JSON; `planimetria` /
  `virtual_tour` / `visual_tour` / `video` accettano temporaneamente sia gli URL
  legacy sia le nuove liste JSON. `GetrixProvider` popola gli array scartando i
  valori vuoti. Le colonne YouTube legacy restano disponibili come fallback
  durante la prima migrazione e non vengono più scritte.
- Dettaglio frontend: sezione "Video" (embed YouTube + colonna `video`, con
  `<video>` per i file diretti .mp4/.webm/.ogg/.mov) e sezione "Tour virtuale"
  (embed `virtual_tour`). Nuove chiavi `pages.immobili.detail.video` /
  `.virtual_tour` in it/en.
- Form backend per la creazione degli immobili riallineato al gestionale storico:
  layout 9/3 a schede, tutti i campi commerciali e tecnici, tassonomie dipendenti,
  stato editoriale e upload immagini; esclusi `customer_id` e `company_id`.
- Form media backend: YouTube e Virtual Tour accettano più URL ordinabili; in
  modifica fotografie e planimetrie sono gestite in sezioni separate, mostrano
  l'anteprima dentro il drag&drop e usano una griglia responsive fino a quattro
  elementi per riga.
- Paginazione di lista e venduti tramite `pagination()` di `wonder-image/app`.
  `ImmobileQuery` rifattorizzato a query SQL su singola tabella (`where()` /
  `order()` + `sqlCount`/`sqlSelect`): i conteggi della paginazione sono corretti
  anche con filtri attivi. Il parametro di pagina passa da `pagina` a `page`.

### Corretto
- Errore fatale nella lista immobili con più di una pagina di risultati (closure
  `$pageUrl` non definita in `view/pages/frontend/list.php`).
- Aggiornamento Forge della tabella `immobili`: eliminate le decine di
  `VARCHAR(1000)` implicite che superavano il limite InnoDB di 65.535 byte;
  ogni stringa ha ora una lunghezza SQL esplicita oppure usa `TEXT`.
- Migrazione media senza perdita dati: gli URL esistenti non vengono convertiti
  direttamente in JSON e `youtube_1..4` non vengono eliminati prima del
  backfill; la lettura espone comunque il nuovo formato ad array.
