# Modello immobile

Tutti i gestionali confluiscono nelle stesse tabelle. La distinzione avviene tramite `provider`
(getrix/gestim/…) e `feed_source_id` (feed di origine).

## Tabella `immobili` (principali colonne)

- **Origine**: `provider`, `feed_source_id`, `external_id`, `creator_type`, `creator_id`, `nome`,
  `external_modified_at`, `synced_at`, `feed_deleted`.
- **Stato**: `stato`, `visible`, `evidence`, `sold` (lo stato del form viene normalizzato nei flag
  usati dal catalogo).
- **Classificazione**: `categoria_id`, `macrotipologia_id`, `tipologia_id` — **FK intere**
  (`INT`, `ON DELETE SET NULL`) all'id delle tassonomie canoniche.
- **Luogo**: `comune_id`, `quartiere_id`, `quartiere_zona_id` — **FK intere** alle tassonomie
  canoniche; più `strada`, `indirizzo`, `civico`, `cap`, `note`, coordinate e flag di pubblicazione
  indirizzo. I nomi risolti sono denormalizzati in `comune_nome`/`tipologia_nome` (vedi *Derivati*).
- Gli altri `*_id` (`contratto_id`, `cucina_id`, `riscaldamento_id`, …) **non** sono FK: sono
  enum di attributo (VARCHAR) risolti tramite le mappe di `Support\ImmobileForm`.
- **Contratto**: `contratto_id` (`V`=vendita, `A`=affitto), `prezzo`, `cauzione`,
  `durata_contratto_id`, `spese_mensili`, `spese_riscaldamento`, `reddito`, …
- **Caratteristiche**: superfici e locali, stato/costruzione, cucina/arredamento/box, accessori,
  esterni, piani/esposizione, riscaldamento/acqua calda e classe energetica.
- **Media** (esposti come array di URL — vuoti = `[]`): `youtube` (embed YouTube),
  `planimetria`, `virtual_tour`, `visual_tour`, `video`. Nella prima migrazione
  `youtube` è JSON, mentre gli altri campi restano `TEXT` compatibili sia con
  l'URL singolo storico sia con la lista JSON; `youtube_1..4` sono mantenuti
  temporaneamente come fallback di sola lettura fino al backfill.
- **Derivati**: `slug`, `comune_nome`, `tipologia_nome`; URL e QR code vengono ricostruiti in lettura.
- **`attributi`** (JSON): attributi estesi/polimorfici (dotazioni, impianti, e — per i provider che
  forniscono nomi anziché codici — `comune`, `provincia`, `tipologia`).

## Tabelle correlate

- `immobili_immagini`: `immobile_id`, `tipo` (F/P), `planimetria`, `position`, `titolo`, `source_url`
  (URL remota a massima risoluzione), `file` (originale scaricato), `upload` (immagine caricata a
  mano), `resized`. Il backend presenta fotografie e planimetrie come due sezioni, ma le sincronizza
  insieme su questa relazione per evitare cancellazioni incrociate. Vedi
  [Immagini e media](immagini-e-media.md).
- `immobili_descrizioni`: `immobile_id`, `lingua`, `titolo`, `testo`, `testo_breve` (una riga per
  lingua → base del bilingua).
- `immobili_sync_log`: storico delle sincronizzazioni (file importato, conteggi, esito).

## Tassonomie (canoniche, provider-agnostiche)

`immobili_categorie`, `immobili_macrotipologie`, `immobili_tipologie`, `immobili_regioni`,
`immobili_province`, `immobili_comuni`, `immobili_quartieri`, `immobili_quartieri_zone`.

Ogni tabella ha **una sola riga per entità reale**, condivisa da tutti i gestionali: un comune
importato da Getrix e uno da Gestim sono la **stessa** riga. La chiave canonica è nostra —
`chiave` (slug, es. `residenziale` / `appartamento`) per categorie/macro/tipologie e regioni,
`cod_catastale` (ISTAT) per i comuni, `sigla` per le province. I codici nativi dei gestionali
vivono nelle colonne mappa `getrix_id` / `gestim_id`, estendibili con nuovi provider. Le relazioni
interne (`categoria_id`, `regione_id`, `provincia_id`, `comune_id`, …) sono **FK intere**.

Popolamento: **Getrix** semina il canonico (`syncTaxonomies` fa upsert per chiave naturale
riempiendo `getrix_id`); **Gestim** — che espone nomi e non codici — aggancia le righe esistenti
**per nome** (nessuna creazione automatica). Risoluzione via `Support\Taxonomy`:
`byProviderCode(model, provider, code)` (codice nativo → id canonico) e `nomeById(model, id)`.

## Feed

`immobili_feed`: `name`, `provider`, `active`, credenziali (`code`, `feed_url`, `username`,
`password`), opzioni (`save_images`, `default_*`), `last_sync_at`, `last_sync_status`.

`immobili_settings` (singleton): **centrale di controllo** del modulo — vedi
[Impostazioni](../configurazione/impostazioni.md). Scheda PDF (`pdf_logo`, `pdf_color_primary`,
`pdf_color_secondary`, `pdf_font`, `pdf_font_bold`, `pdf_facts`) e scheda web (`scheda_facts`).
`pdf_logo` salva la chiave della variante di **Media → Logo**; i font provengono dall'elenco
completo `Wonder\App\Support\FpdfFonts`; `pdf_facts` / `scheda_facts` sono liste ordinate di
attributi scelti dal catalogo condiviso `Support\AttributeCatalog`.

## Sincronizzazione tra ambienti (export/import)

L'unica tabella che partecipa a `forge export` / `forge import` è **`immobili_feed`** (la
configurazione dei feed). Immobili, tassonomie, immagini, descrizioni e log **non** si sincronizzano
tra locale e produzione: ogni ambiente popola i propri dati eseguendo la sincronizzazione dei feed.
