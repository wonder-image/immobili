<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Document;

use Wonder\Pdf;
use Wonder\Plugin\Immobili\Pdf\PdfCache;
use Wonder\Plugin\Immobili\Pdf\PdfContext;
use Wonder\Plugin\Immobili\Pdf\Support\Color;
use Wonder\Plugin\Immobili\Pdf\Support\PdfText;

/**
 * Base dei documenti PDF dell'immobile. Possiede un `Wonder\Pdf` configurato
 * (orientamento, margini, font, metadati) e offre helper per colori/testo e per
 * l'output (stream/download/save). Le sottoclassi implementano solo `render()`.
 */
abstract class ImmobileDocument
{
    protected Pdf $pdf;

    /**
     * @param object $immobile  oggetto presentato (ImmobilePresenter::present)
     * @param array<string, mixed> $row  riga grezza dell'immobile
     * @param array<string, mixed> $config  config del documento (PdfConfig)
     */
    public function __construct(
        protected readonly object $immobile,
        protected readonly array $row,
        protected readonly PdfContext $ctx,
        protected readonly array $config,
    ) {
        $this->pdf = new Pdf($this->orientation());
        $this->boot();
    }

    /** 'P' verticale | 'L' orizzontale. */
    abstract protected function orientation(): string;

    /** Margini [sinistra, alto, destra] in mm (i poster li sovrascrivono a 0). */
    protected function margins(): array
    {
        return [10.0, 10.0, 10.0];
    }

    /** Prefisso del nome file (es. 'scheda'). */
    abstract protected function prefix(): string;

    /** Disegna il contenuto sul PDF. */
    abstract protected function render(): void;

    protected function boot(): void
    {
        [$left, $top, $right] = $this->margins();
        $this->pdf->SetMargins($left, $top, $right);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->LoadFont($this->ctx->font ?: 'helvetica', $this->ctx->fontBold);
        $this->pdf->AliasNbPages();
        $this->pdf->AddPage();

        $title = (string) ($this->immobile->titolo ?? $this->immobile->prettyName ?? 'Immobile');
        $this->pdf->SetTitle(PdfText::encode($title));
        $this->pdf->SetAuthor(PdfText::encode($this->ctx->contacts->name));
        $this->pdf->SetCreator('wonder-image/immobili');
    }

    /** Genera il PDF e lo ritorna come stringa (senza emettere header). */
    public function build(): string
    {
        $this->render();

        return $this->pdf->Output('S');
    }

    public function stream(?string $name = null): void
    {
        $this->emit($this->cachedBytes(), $name, 'inline');
    }

    public function download(?string $name = null): void
    {
        $this->emit($this->cachedBytes(), $name, 'attachment');
    }

    public function save(string $path): string
    {
        file_put_contents($path, $this->build());

        return $path;
    }

    /**
     * Byte del PDF passando dalla cache: se esiste già una versione con la stessa
     * firma la serve, altrimenti rigenera e salva. La `build()` (parte costosa:
     * render + immagini) viene eseguita solo su cache miss.
     */
    public function cachedBytes(): string
    {
        return PdfCache::remember(
            $this->cacheKind(),
            $this->cacheKey(),
            $this->signature(),
            fn (): string => $this->build(),
        );
    }

    /** Tipo di documento per il nome file in cache (default = prefisso). */
    protected function cacheKind(): string
    {
        return $this->prefix();
    }

    /** Chiave di cache per-immobile: external_id, poi id. Vuota => niente cache. */
    protected function cacheKey(): string
    {
        return (string) ($this->row['external_id'] ?? ($this->row['id'] ?? ''));
    }

    /**
     * Firma degli input che determinano il PDF: dati presentati (immagini e
     * descrizione inclusi), riga grezza (esclusa `synced_at`, che cambia ad ogni
     * sync senza toccare il contenuto), branding/contatti e config. Calcolata dai
     * dati già caricati: nessuna query aggiuntiva.
     */
    public function signature(): string
    {
        $presented = get_object_vars($this->immobile);
        unset($presented['geo_json'], $presented['gmaps']);

        $row = $this->row;
        unset($row['synced_at']);

        return md5(serialize([
            'immobile' => $presented,
            'row'      => $row,
            'branding' => [
                $this->ctx->primary->rgb(),
                $this->ctx->secondary->rgb(),
                $this->ctx->font,
                $this->ctx->fontBold,
                $this->ctx->logo,
                $this->ctx->contacts,
            ],
            'config'   => $this->config,
            'kind'     => $this->cacheKind(),
        ]));
    }

    private function emit(string $pdf, ?string $name, string $disposition): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: '.$disposition.'; filename="'.$this->filename($name).'"');
            header('Content-Length: '.strlen($pdf));
        }

        echo $pdf;
    }

    protected function filename(?string $name): string
    {
        if ($name !== null && $name !== '') {
            return $name;
        }

        $slug = (string) ($this->immobile->slug ?? ($this->row['slug'] ?? ($this->immobile->id ?? 'immobile')));

        return $this->prefix().'-'.$slug.'.pdf';
    }

    protected function fill(Color $color): void
    {
        $this->pdf->SetFillColor($color->r, $color->g, $color->b);
    }

    protected function text(Color $color): void
    {
        $this->pdf->SetTextColor($color->r, $color->g, $color->b);
    }

    protected function draw(Color $color): void
    {
        $this->pdf->SetDrawColor($color->r, $color->g, $color->b);
    }

    protected function t(string $value, bool $upper = false): string
    {
        return PdfText::encode($value, $upper);
    }
}
