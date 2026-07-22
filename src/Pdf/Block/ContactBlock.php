<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Block;

use Wonder\Pdf;
use Wonder\Plugin\Immobili\Pdf\PdfContext;
use Wonder\Plugin\Immobili\Pdf\Support\PdfText;

/**
 * Righe di contatto (header e footer) prese dai dati aziendali del sito. Quali
 * elementi mostrare è deciso dai toggle di configurazione; i valori vengono da
 * `PdfContext::$contacts`. Il colore/testo corrente è impostato dal chiamante.
 */
final class ContactBlock
{
    /**
     * Riga contatti a piè di pagina (tel · email · sito), centrata.
     *
     * @param array<string, bool> $toggles chiavi: tel, email, site
     */
    public static function footer(Pdf $pdf, PdfContext $ctx, array $toggles): void
    {
        $parts = [];

        if (($toggles['tel'] ?? false) && $ctx->contacts->tel !== '') {
            $parts[] = 'Tel: '.$ctx->contacts->tel;
        }
        if (($toggles['email'] ?? false) && $ctx->contacts->email !== '') {
            $parts[] = 'Email: '.$ctx->contacts->email;
        }
        if (($toggles['site'] ?? false) && $ctx->contacts->site !== '') {
            $parts[] = $ctx->contacts->site;
        }

        if ($parts === []) {
            return;
        }

        $pdf->Font(8);
        $pdf->SetXY(10, -14);
        $pdf->MultiCell(0, 4, PdfText::encode(implode('   ·   ', $parts)), 0, 'C', false);
    }

    /**
     * Indirizzo dell'ufficio nell'header (allineato a destra).
     */
    public static function headerAddress(Pdf $pdf, PdfContext $ctx, float $x, float $y, float $w): void
    {
        if ($ctx->contacts->address === '') {
            return;
        }

        $pdf->Font(8);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($w, 4, PdfText::encode("UFFICIO:\n".$ctx->contacts->address), 0, 'R', false);
    }
}
