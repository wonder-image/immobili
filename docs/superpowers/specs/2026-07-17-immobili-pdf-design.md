# Generazione PDF immobile con FPDF (scheda + cartelli) — helper riutilizzabili

- **Data**: 2026-07-17
- **Modulo**: `wonder-image/immobili`
- **Stato**: design approvato, in attesa di piano di implementazione

## Obiettivo

Generare i PDF del modulo con **FPDF** (via la classe `Wonder\Pdf` del framework),
sul modello del progetto di riferimento
`clients/rclass/projects/gest-rclass-it/custom/function/platform/pdf.php`, ma con
un'architettura di **classi di aiuto** che renda i documenti *modificabili,
ottimizzati, espandibili e riutilizzabili*.

Tre documenti:

1. **Scheda immobile** — A4 verticale: dati principali, galleria foto, descrizione.
   Sostituisce l'attuale pagina HTML print di `http/frontend/pdf.php`.
2. **Cartello immobile** — A4 orizzontale: tipo contratto in grande, testo breve,
   QR code, classe energetica, telefono. Materiale d'agenzia.
3. **Cartello vetrina** — A4 orizzontale con foto di copertina a tutto sfondo +
   overlay `VENDUTO`/`AFFITTATO`. Materiale d'agenzia.

## Contesto (stato attuale)

- Il framework espone `Wonder\Pdf` (estende `Fpdf\Fpdf`) con helper `LoadFont`,
  `Font`, `FontBold`, trasformazioni (`Rotate`, `Scale`, `Skew`, `Mirror`,
  `StartTransform`/`StopTransform`) e `MultiCellHeight`
  (`vendor/wonder-image/app/class/Pdf.php`). FPDF è disponibile via Composer
  (`fpdf/fpdf`). **Non si tocca** — è vendor.
- Oggi `http/frontend/pdf.php` è una **pagina HTML print-friendly**, non un PDF
  nativo (con nota «export PDF nativo via `Wonder\Pdf` può essere aggiunto in
  seguito»). Rotta già registrata: `/immobili/{slug}/pdf/` → `immobili.pdf`.
- I dati dell'immobile arrivano da `Services/ImmobilePresenter::present($row)`
  (oggetto con `titolo`, `prettyName`, `prettyAddress`, `prezzo`, `superficie`,
  `locali`, `camere`, `bagni`, `classe`, `tipologia`, `contratto`, `descrizione`,
  `descrizione_breve`, `images[]`, `planimetrie[]`, `cover`, `qrcode`, `sold`, …).
- Il **QR code** è già generato in sync (`Services/FeedSyncService::buildQrCode`
  → `createQrCode()` del framework) e salvato in `$immobile->qrcode`.
- Il riferimento presenta forte **duplicazione**, che questa architettura elimina:
  - il calcolo *fit-and-center* dell'immagine (getimagesize + scala a max W/H +
    centratura) compare ~5 volte;
  - il **box classe energetica** (immagine scala + classe + IPE) è identico tra
    Cartello e Vetrina (~40 righe duplicate);
  - il setup PDF (margini, `LoadFont`, colori, `AliasNbPages`, `AddPage`,
    `SetTitle`/`SetAuthor`/`SetCreator`) è ripetuto nei tre documenti.

## Decisioni (approvate)

1. **Documenti (scope)**: tutti e tre — scheda, cartello, vetrina.
2. **Architettura**: *Documenti + Blocchi riutilizzabili* (composizione). I
   documenti compongono blocchi condivisi; nessun boilerplate ripetuto.
3. **Entry point**:
   - **Scheda** pubblica sulla rotta esistente `/immobili/{slug}/pdf/` (l'handler
     passa da HTML a stream FPDF nativo);
   - **Cartello** e **Vetrina** in **backend**, rotte protette dal permesso
     admin/`immobili_manager`, download on-demand per singolo immobile.
4. **Fonti dati**:
   - **Branding** (logo, colori, font) → model `Settings` del modulo
     (`immobili_settings`): `pdf_logo_url`, `pdf_color_primary/secondary`,
     `pdf_font/pdf_font_bold`.
   - **Contatti** (nome, telefono, email, sito, indirizzo) → **config sito**, cioè
     il runtime global `$SOCIETY` (model `Society` del framework: `name`, `tel`,
     `cel`, `email`, `site`, `prettyAddress`, `gmaps`).
   - **Immobile** → `ImmobilePresenter` + riga grezza per i campi extra della
     scheda.
5. **Configurabilità (lato sviluppatore, in codice)**: contatti mostrati,
   header/footer, numero di immagini della scheda e dettagli mostrati sono
   configurabili via il **config del modulo** (`config/module.php`, blocco `pdf`)
   sovrascrivibile dal sito in `custom/config/modules/immobili.php`. **Nessun
   nuovo campo backend**: la superficie di override è il config.
   - *dettagli scheda* = array **ordinato** di chiavi (i default filtrano alle
     chiavi note);
   - *header/footer* = **toggle** booleani dei singoli elementi; i valori dei
     contatti vengono sempre da `$SOCIETY`.

## Architettura

Tutto sotto `src/Pdf/`, namespace `Wonder\Plugin\Immobili\Pdf`.

```
src/Pdf/
  PdfRenderer.php            façade statica usata dagli handler
  PdfContext.php             VO immutabile: branding + contatti + colori risolti
  PdfConfig.php              lettore tipizzato del config `pdf` (default + override sito)
  PdfFacts.php               mappa immobile → coppie [label, valore] della scheda
  Support/
    Color.php                hex → {r,g,b} + colore di contrasto (neutral)
    ImageFitter.php          fit-and-center → {x,y,w,h} (pura matematica, no FPDF)
    PdfText.php              encode UTF-8 → encoding font FPDF (+ maiuscolo opz.)
  Block/
    LogoBlock.php            logo azienda con fit
    EnergyClassBox.php       box classe energetica (immagine scala + classe + IPE)
    QrBlock.php              QR code
    ContactBlock.php         header (indirizzo) e footer (tel/email/sito) contatti
  Document/
    ImmobileDocument.php     base astratta: possiede Wonder\Pdf + PdfContext + config + dati
    SchedaImmobile.php       A4 verticale
    CartelloImmobile.php     A4 orizzontale
    CartelloVetrina.php      A4 orizzontale + overlay VENDUTO/AFFITTATO
```

### Responsabilità (una per unità)

- **`PdfContext::build()`** — legge **una sola volta** `Settings` (logo/colori/font)
  e `$SOCIETY` (contatti/indirizzo), risolve i colori con `Color`, espone
  proprietà pronte. I documenti ignorano *da dove* arriva la config. Fallback
  difensivo se `Settings`/`$SOCIETY` mancano (helvetica, bianco/nero, contatti
  vuoti).
- **`PdfConfig`** — legge `Immobili::config('pdf', DEFAULTS)` (default del modulo
  fusi con l'override del sito). Espone `scheda()`, `cartello()`, `vetrina()` con
  `images`, `facts` (ordinati, filtrati alle chiavi note), `header`/`footer`
  (toggle), `contacts`, `energy`.
- **`ImmobileDocument`** (astratta) — costruisce e configura il `Wonder\Pdf`
  (orientamento per documento, margini, `LoadFont`, colori base, metadati
  `SetTitle`/`SetAuthor`/`SetCreator`, `AliasNbPages`, `AddPage`); espone
  `stream()/download()/save()`; dichiara `abstract protected function render(): void`.
- I **3 documenti** implementano solo `render()` componendo blocchi + (scheda)
  `PdfFacts`.
- I **Block** sono renderer isolati che disegnano su un `Pdf` passato per argomento
  (nessuno stato condiviso). `EnergyClassBox` sostituisce le ~40 righe duplicate.
- **`PdfRenderer`** — wiring di `PdfContext::build()` + `PdfConfig` + istanza
  documento. È l'unico punto toccato dagli handler.

### Firme

```php
// Support/ — puro, unit-testabile senza FPDF
Color::fromHex(string $hex, array $fallback = [0,0,0]): self;   // ->rgb(): array{int,int,int}; ->neutral(): self
ImageFitter::contain(string $file, float $x, float $y, float $maxW, float $maxH, string $align = 'center'): array; // {x,y,w,h}; vuoto se file mancante
PdfText::encode(string $text, bool $upper = false): string;     // UTF-8 → encoding font FPDF (accenti IT)

// Block/ — disegnano su un Pdf passato
LogoBlock::render(Pdf $pdf, string $logo, float $x, float $y, float $maxW, float $maxH, string $align = 'left'): void;
EnergyClassBox::render(Pdf $pdf, PdfContext $ctx, float $x, float $y, float $width, string $classe, string $ipe): void;
QrBlock::render(Pdf $pdf, string $qr, float $x, float $y, float $size): void;
ContactBlock::footer(Pdf $pdf, PdfContext $ctx, array $toggles): void;
ContactBlock::headerAddress(Pdf $pdf, PdfContext $ctx, float $x, float $y): void;

// Nucleo
PdfContext::build(): self;
PdfFacts::build(array $row, object $presented, array $keys): array;  // [ ['label'=>..,'value'=>..], … ] ordinati, non vuoti
abstract ImmobileDocument { protected function boot(): void; abstract protected function render(): void;
    public function stream(?string $name=null): void; public function download(?string $name=null): void;
    public function save(string $path): string; protected function filename(string $prefix): string;
    protected function orientation(): string; }

// Façade
PdfRenderer::scheda(array $row): SchedaImmobile;
PdfRenderer::cartello(array $row): CartelloImmobile;
PdfRenderer::vetrina(array $row, bool $sold = false): CartelloVetrina;
```

## Flusso dati

```
Settings (modulo) ─┐
                   ├─► PdfContext::build() ─┐
$SOCIETY (sito) ───┘                        │
config/module.php + override sito ─► PdfConfig
                                            │
Immobile row → ImmobilePresenter ──────────►│
                       └─► PdfFacts (scheda) ┘
                                            ▼
        PdfRenderer → ImmobileDocument.render() → Wonder\Pdf → Output(S) → header + echo
                                            ▲
                                            └─ Block/*, Support/*
```

Gli handler passano la **riga grezza** dell'immobile a `PdfRenderer`, che al suo
interno costruisce il presenter (per i campi formattati) e usa la riga grezza per
i `PdfFacts` (campi non esposti dal presenter: `ipe`, `anno_costruzione`,
`piani_edificio`, `piano`, `riscaldamento`, `cucina`, `posti_auto`, `spese`,
`zona`, `riferimento` — letti da colonne `immobili` o da `attributi`).

## Configurabilità — default di `config/module.php`

```php
'pdf' => [
    'scheda' => [
        'images' => 6,
        'facts'  => [
            'riferimento','zona','contratto','prezzo','spese','tipologia',
            'anno_costruzione','piani_edificio','piano','classe','ipe',
            'superficie','locali','camere','bagni','cucina','riscaldamento','posti_auto',
        ],
        'header' => ['logo' => true, 'address' => true],
        'footer' => ['tel' => true, 'email' => true, 'site' => true],
    ],
    'cartello' => ['header' => ['logo' => true], 'contacts' => ['tel' => true], 'energy' => true],
    'vetrina'  => ['header' => ['logo' => true], 'contacts' => ['tel' => true], 'energy' => true],
],
```

Il sito sovrascrive in `custom/config/modules/immobili.php`. Le **etichette** dei
dettagli passano da `__t(...)` nel namespace traduzioni del modulo
(`pages/components/immobili/pdf`), bilingua it/en.

## Entry point (rotte e handler)

- **Scheda (pubblica)** — riscrivere `http/frontend/pdf.php`: da pagina HTML a
  `PdfRenderer::scheda($row)->stream()`. Stessa rotta `/immobili/{slug}/pdf/`
  (`immobili.pdf`). Il bottone "print" del dettaglio continua a puntare lì.
- **Cartello / Vetrina (backend)** — nuove rotte in
  `config/routes/route.backend.php` protette dal permesso admin/`immobili_manager`,
  handler in `http/backend/immobile/`:
  - `/immobili/{id}/cartello/` → `cartello.php` → `PdfRenderer::cartello($row)->download()`
  - `/immobili/{id}/vetrina/`  → `vetrina.php`  → `PdfRenderer::vetrina($row, sold)->download()`
    (flag `sold` da query per l'overlay VENDUTO/AFFITTATO).

Nome file dallo slug immobile (`$immobile->dir`): es. `scheda-{dir}.pdf`,
`cartello-{dir}.pdf`, `vetrina-{dir}.pdf`. Nessuna dipendenza da helper esterni.

## Encoding testo & asset

- **`PdfText::encode`** è essenziale: FPDF (core + font `$FONT_FPDF`) non è UTF-8;
  senza conversione gli accenti italiani si rompono. Sostituisce il `printPDF()`
  del riferimento. In fase di piano si verifica se il framework espone già un
  helper equivalente; altrimenti si scrive (piccolo, testato).
- **Immagine scala energetica**: spedita come asset del modulo in
  `resources/assets/img/classe-energetica.png`, risolta con
  `Immobili::assetPath('img/classe-energetica.png')` (path filesystem, richiesto
  da `FPDF::Image`).
- **Font**: `boot()` chiama `$pdf->LoadFont($ctx->font, $ctx->fontBold)`, con
  fallback `helvetica` se i `Settings` non hanno font.

## Error handling

- **Render-to-string poi emit**: generare con `Output('S')`, poi emettere header +
  echo. Un errore a metà build non corrompe lo stream.
- **Immobile inesistente**: scheda → redirect alla lista (come oggi); backend →
  404/redirect con messaggio.
- **Asset mancanti** (logo/qr/cover/scala): i blocchi saltano senza fatal
  (`ImageFitter::contain` ritorna geometria vuota se il file non esiste; i blocchi
  guardano il path vuoto).
- **`Settings`/`$SOCIETY` assenti**: `PdfContext` va in fallback e il PDF esce
  comunque.
- **Autorizzazione**: le rotte backend verificano il permesso
  admin/`immobili_manager`.

## Test

- **Unit** (senza FPDF): `Color` (parsing hex, contrasto), `ImageFitter`
  (scala/centra, file mancante), `PdfText` (accenti), `PdfFacts` (ordine + filtro
  vuoti + risoluzione label), `PdfConfig` (default + merge override).
- **Smoke/integrazione**: generare i 3 documenti in stringa (`Output('S')`) con
  una fixture immobile; assert output che inizia con `%PDF`, lunghezza non banale,
  nessuna eccezione, orientamento/pagine attesi. Allineamento al setup `tests/`
  del modulo (`module.json` → `paths.tests`).

## Fuori scope

- Editor WYSIWYG del layout / configurazione da backend admin (si è scelto
  l'override in codice).
- Modifica di `Wonder\Pdf` o di qualsiasi file in `vendor/`.
- Nuovi campi contatto duplicati nel modulo (i contatti restano su `$SOCIETY`).
- Generazione batch/bulk dei cartelli (on-demand per singolo immobile).
```