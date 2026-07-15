# Changelog

Tutte le modifiche rilevanti a `wonder-image/immobili` sono documentate qui.
Il formato segue [Keep a Changelog](https://keepachangelog.com/it/1.0.0/).

## [1.0.0] - non ancora rilasciato

### Aggiunto
- Struttura del modulo (entrypoint `Immobili`, routing frontend/backend/api, i18n it/en).
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
- Paginazione di lista e venduti tramite `pagination()` di `wonder-image/app`.
  `ImmobileQuery` rifattorizzato a query SQL su singola tabella (`where()` /
  `order()` + `sqlCount`/`sqlSelect`): i conteggi della paginazione sono corretti
  anche con filtri attivi. Il parametro di pagina passa da `pagina` a `page`.

### Corretto
- Errore fatale nella lista immobili con più di una pagina di risultati (closure
  `$pageUrl` non definita in `view/pages/frontend/list.php`).
