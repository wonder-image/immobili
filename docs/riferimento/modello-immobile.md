# Modello immobile

Tutti i gestionali confluiscono nelle stesse tabelle. La distinzione avviene tramite `provider`
(getrix/gestim/…) e `feed_source_id` (feed di origine).

## Tabella `immobili` (principali colonne)

- **Origine**: `provider`, `feed_source_id`, `external_id`, `nome`, `external_modified_at`,
  `synced_at`, `feed_deleted`.
- **Stato**: `visible`, `evidence`, `sold` (flag manuali, preservati alla sync).
- **Classificazione**: `categoria_id`, `macrotipologia_id`, `tipologia_id` (codici nativi del
  gestionale, risolti via tassonomie).
- **Luogo**: `comune_id`, `quartiere`, `quartiere_zona`, `zona`, `strada`, `indirizzo`, `civico`,
  `cap`, `latitudine`, `longitudine`, `zoom`, `pub_indirizzo`, `pub_civico`, `pub_mappa`.
- **Contratto**: `contratto_id` (`V`=vendita, `A`=affitto), `prezzo`, `prezzo_affitto`,
  `trattativa_riservata`, `spese_mensili`, `asta`, `pregio`, `reddito`, …
- **Caratteristiche**: `superficie`, `n_locali`, `n_camere`, `n_bagni`, `n_terrazzi`, `n_balconi`,
  `n_posti_auto`, `piano`, `piani_edificio`, `anno_costruzione`, `classe_energetica`, `ipe`, …
- **Media**: `youtube_1..4`, `planimetria`, `virtual_tour`, `visual_tour`, `video`.
- **Derivati**: `dir` (slug), `url`, `qrcode`.
- **`attributi`** (JSON): attributi estesi/polimorfici (dotazioni, impianti, e — per i provider che
  forniscono nomi anziché codici — `comune`, `provincia`, `tipologia`).

## Tabelle correlate

- `immobili_immagini`: `immobile_id`, `tipo` (F/P), `planimetria`, `position`, `titolo`, `source_url`
  (URL remota a massima risoluzione), `file` (originale scaricato), `upload` (immagine caricata a
  mano), `resized`. Vedi [Immagini e media](immagini-e-media.md).
- `immobili_descrizioni`: `immobile_id`, `lingua`, `titolo`, `testo`, `testo_breve` (una riga per
  lingua → base del bilingua).
- `immobili_sync_log`: storico delle sincronizzazioni (file importato, conteggi, esito).

## Tassonomie

`immobili_categorie`, `immobili_macrotipologie`, `immobili_tipologie`, `immobili_regioni`,
`immobili_province`, `immobili_comuni`, `immobili_quartieri`, `immobili_quartieri_zone`. Ogni riga
porta `provider` + `codice` nativo: la risoluzione delle etichette avviene tramite
`Support\Taxonomy` sulla coppia (provider, codice).

## Feed

`immobili_feed`: `name`, `provider`, `active`, credenziali (`code`, `feed_url`, `username`,
`password`), opzioni (`save_images`, `default_*`), `last_sync_at`, `last_sync_status`.

`immobili_settings` (singleton): impostazioni PDF **globali** (comuni a tutti i feed) — `pdf_logo`,
`pdf_color_primary`, `pdf_color_secondary`, `pdf_font`, `pdf_font_bold` (font dall'array `$FONT_FPDF`).

## Sincronizzazione tra ambienti (export/import)

L'unica tabella che partecipa a `forge export` / `forge import` è **`immobili_feed`** (la
configurazione dei feed). Immobili, tassonomie, immagini, descrizioni e log **non** si sincronizzano
tra locale e produzione: ogni ambiente popola i propri dati eseguendo la sincronizzazione dei feed.
