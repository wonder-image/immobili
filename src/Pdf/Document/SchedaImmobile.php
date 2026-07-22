<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Document;

use Wonder\Plugin\Immobili\Pdf\Block\ContactBlock;
use Wonder\Plugin\Immobili\Pdf\Block\LogoBlock;
use Wonder\Plugin\Immobili\Pdf\PdfFacts;
use Wonder\Plugin\Immobili\Pdf\Support\Color;
use Wonder\Plugin\Immobili\Pdf\Support\ImageFitter;

/**
 * Scheda immobile A4 verticale: header con logo e indirizzo ufficio, titolo,
 * tabella dei dettagli (colonna sinistra), galleria foto (colonna destra),
 * descrizione e footer con i contatti. Dettagli, numero di immagini e toggle di
 * header/footer arrivano dalla config.
 */
final class SchedaImmobile extends ImmobileDocument
{
    private const GREY_ROW = [245, 245, 245];
    private const GREY_TEXT = [90, 90, 90];

    protected function orientation(): string
    {
        return 'P';
    }

    protected function prefix(): string
    {
        return 'scheda';
    }

    protected function render(): void
    {
        $this->header();
        $this->title();

        $columnsTop = 50.0;
        $this->facts($columnsTop);
        $this->gallery($columnsTop);
        $this->footer();
    }

    private function header(): void
    {
        $header = (array) ($this->config['header'] ?? []);

        $this->fill($this->ctx->primary);
        $this->pdf->Rect(0, 0, 210, 25, 'F');

        if (($header['logo'] ?? false) && $this->ctx->logo !== '') {
            LogoBlock::render($this->pdf, $this->ctx->logo, 10, 6, 90, 13, 'left');
        }

        if ($header['address'] ?? false) {
            $this->text($this->ctx->primary->neutral());
            ContactBlock::headerAddress($this->pdf, $this->ctx, 120, 5, 80);
        }
    }

    private function title(): void
    {
        $this->text(new Color(20, 20, 20));
        $this->pdf->FontBold(16);
        $this->pdf->SetXY(10, 30);
        $this->pdf->MultiCell(190, 7, $this->t((string) ($this->immobile->titolo ?? '')), 0, 'L', false);

        $address = (string) ($this->immobile->prettyAddress ?? '');
        if ($address !== '') {
            $this->text(new Color(...self::GREY_TEXT));
            $this->pdf->Font(9);
            $this->pdf->SetXY(10, $this->pdf->GetY() + 1);
            $this->pdf->MultiCell(190, 4.5, $this->t($address), 0, 'L', false);
        }
    }

    private function facts(float $top): void
    {
        $keys = array_values(array_filter(
            (array) ($this->config['facts'] ?? []),
            static fn ($k): bool => is_string($k),
        ));

        $facts = PdfFacts::build($this->row, $this->immobile, $keys);

        $x = 10.0;
        $width = 88.0;
        $rowHeight = 6.0;
        $y = $top;
        $i = 0;

        foreach ($facts as $fact) {
            if ($i % 2 === 0) {
                $this->pdf->SetFillColor(...self::GREY_ROW);
                $this->pdf->Rect($x, $y, $width, $rowHeight, 'F');
            }

            $this->text(new Color(...self::GREY_TEXT));
            $this->pdf->Font(9);
            $this->pdf->SetXY($x + 1.5, $y + 1.5);
            $this->pdf->MultiCell($width * 0.5, 4, $this->t($fact['label']), 0, 'L', false);

            $this->text(new Color(20, 20, 20));
            $this->pdf->FontBold(9);
            $this->pdf->SetXY($x + $width * 0.5, $y + 1.5);
            $this->pdf->MultiCell($width * 0.5 - 1.5, 4, $this->t($fact['value']), 0, 'R', false);

            $y += $rowHeight;
            $i++;
        }

        $description = (string) ($this->immobile->descrizione ?? '');
        if ($description !== '') {
            $this->text(new Color(30, 30, 30));
            $this->pdf->Font(9);
            $this->pdf->SetXY($x, $y + 5);
            $this->pdf->MultiCell($width, 4.5, $this->t($description), 0, 'L', false);
        }
    }

    private function gallery(float $top): void
    {
        $images = is_array($this->immobile->images ?? null) ? $this->immobile->images : [];
        $max = max(0, (int) ($this->config['images'] ?? 6));

        $x = 105.0;
        $width = 95.0;
        $y = $top;
        $count = 0;

        foreach ($images as $image) {
            if ($count >= $max) {
                break;
            }

            $src = (string) ($image['src'] ?? ($image['url'] ?? ($image['thumb'] ?? '')));
            $geom = ImageFitter::contain($src, $x, $y, $width, 63, 'center');

            if ($geom['w'] <= 0.0) {
                continue;
            }

            $this->pdf->Image($src, $geom['x'], $geom['y'], $geom['w'], $geom['h']);
            $y = $geom['y'] + $geom['h'] + 3;
            $count++;
        }
    }

    private function footer(): void
    {
        $this->text(new Color(...self::GREY_TEXT));
        ContactBlock::footer($this->pdf, $this->ctx, (array) ($this->config['footer'] ?? []));
    }
}
