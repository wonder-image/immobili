<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Document;

use Wonder\Plugin\Immobili\Pdf\Block\ContactBlock;
use Wonder\Plugin\Immobili\Pdf\Block\LogoBlock;
use Wonder\Plugin\Immobili\Pdf\PdfFacts;
use Wonder\Plugin\Immobili\Pdf\Support\Color;
use Wonder\Plugin\Immobili\Pdf\Support\ImageFitter;
use Wonder\Plugin\Immobili\Pdf\Support\PdfText;

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
        $factsBottom = $this->facts($columnsTop);
        $this->gallery($columnsTop);
        $this->description($factsBottom);
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

    private function facts(float $top): float
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
            $this->pdf->SetXY($x + 1.5, $y);
            $this->pdf->Cell(
                $width * 0.5 - 1.5,
                $rowHeight,
                $this->fitLine($this->t($fact['label']), $width * 0.5 - 3),
                0,
                0,
                'L'
            );

            $this->text(new Color(20, 20, 20));
            $this->pdf->FontBold(9);
            $this->pdf->SetXY($x + $width * 0.5, $y);
            $this->pdf->Cell(
                $width * 0.5 - 1.5,
                $rowHeight,
                $this->fitLine($this->t($fact['value']), $width * 0.5 - 3),
                0,
                0,
                'R'
            );

            $y += $rowHeight;
            $i++;
        }

        return $y;
    }

    private function gallery(float $top): void
    {
        $images = is_array($this->immobile->images ?? null) ? $this->immobile->images : [];
        $max = max(0, (int) ($this->config['images'] ?? 6));

        $x = 105.0;
        $width = 95.0;
        $gap = 3.0;
        $columns = 2;
        $cellWidth = ($width - $gap) / $columns;
        $cellHeight = 63.0;
        $count = 0;

        foreach ($images as $image) {
            if ($count >= $max) {
                break;
            }

            $src = is_string($image)
                ? $image
                : (string) ($image['src'] ?? ($image['url'] ?? ($image['thumb'] ?? '')));
            $file = ImageFitter::resolve($src);

            $column = $count % $columns;
            $row = intdiv($count, $columns);
            $cellX = $x + ($column * ($cellWidth + $gap));
            $cellY = $top + ($row * ($cellHeight + $gap));

            if ($cellY + $cellHeight > 276) {
                break;
            }

            $geom = ImageFitter::contain($file, $cellX, $cellY, $cellWidth, $cellHeight, 'center');

            if ($geom['w'] <= 0.0) {
                continue;
            }

            $this->pdf->Image($file, $geom['x'], $geom['y'], $geom['w'], $geom['h']);
            $count++;
        }
    }

    private function description(float $factsBottom): void
    {
        $description = PdfText::plain((string) ($this->immobile->descrizione ?? ''));

        if ($description === '') {
            $this->footer();

            return;
        }

        $lineHeight = 4.5;
        $firstY = $factsBottom + 5;
        $this->text(new Color(30, 30, 30));
        $this->pdf->Font(9);

        // MultiCell applica un margine interno su entrambi i lati: usiamo una
        // larghezza di wrapping leggermente più stretta per evitare che FPDF
        // aggiunga righe impreviste oltre il fondo pagina.
        $lines = $this->wrap($this->t($description), 84);
        $firstCapacity = max(0, (int) floor((270 - $firstY) / $lineHeight));
        $firstLines = array_slice($lines, 0, $firstCapacity);

        if ($firstLines !== []) {
            $this->pdf->SetXY(10, $firstY);
            $this->pdf->MultiCell(88, $lineHeight, implode("\n", $firstLines), 0, 'L', false);
        }

        $remaining = array_slice($lines, count($firstLines));
        $this->footer();

        if ($remaining === []) {
            return;
        }

        // Ricomponiamo il testo non entrato nella colonna stretta e lo
        // reimpaginiamo a tutta larghezza nelle pagine successive.
        $remainingLines = $this->wrap(implode(' ', $remaining), 186);
        $pageCapacity = (int) floor((270 - 42) / $lineHeight);

        while ($remainingLines !== []) {
            $pageLines = array_splice($remainingLines, 0, $pageCapacity);

            $this->pdf->AddPage();
            $this->header();
            $this->text(new Color(20, 20, 20));
            $this->pdf->FontBold(14);
            $this->pdf->SetXY(10, 30);
            $this->pdf->Cell(190, 7, $this->t('Descrizione'), 0, 0, 'L');

            $this->text(new Color(30, 30, 30));
            $this->pdf->Font(9);
            $this->pdf->SetXY(10, 42);
            $this->pdf->MultiCell(190, $lineHeight, implode("\n", $pageLines), 0, 'L', false);
            $this->footer();
        }
    }

    /**
     * @return array<int, string>
     */
    private function wrap(string $text, float $width): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $text) ?: [] as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph)) ?: [];
            $line = '';

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }

                $candidate = $line === '' ? $word : $line.' '.$word;

                if ($line !== '' && $this->pdf->GetStringWidth($candidate) > $width) {
                    $lines[] = $line;
                    $line = $word;
                    continue;
                }

                $line = $candidate;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function fitLine(string $text, float $width): string
    {
        if ($this->pdf->GetStringWidth($text) <= $width) {
            return $text;
        }

        $suffix = '...';

        while ($text !== '' && $this->pdf->GetStringWidth(rtrim($text).$suffix) > $width) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text).$suffix;
    }

    private function footer(): void
    {
        $this->text(new Color(...self::GREY_TEXT));
        ContactBlock::footer($this->pdf, $this->ctx, (array) ($this->config['footer'] ?? []));
    }
}
