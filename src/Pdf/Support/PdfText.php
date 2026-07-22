<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Support;

/**
 * Converte testo UTF-8 nell'encoding atteso da FPDF (CP1252 / Windows-1252): i
 * font core e quelli aggiunti via `$FONT_FPDF` non sono UTF-8, quindi senza
 * questa conversione gli accenti italiani (à, è, ì, ò, ù…) verrebbero resi male.
 *
 * Sostituisce il `printPDF()` del progetto di riferimento.
 */
final class PdfText
{
    /**
     * @param bool $upper se true, applica il maiuscolo (UTF-8-aware) prima della
     *                    conversione, così gli accenti maiuscoli restano corretti.
     */
    public static function encode(string $text, bool $upper = false): string
    {
        if ($text === '') {
            return '';
        }

        if ($upper) {
            $text = mb_strtoupper($text, 'UTF-8');
        }

        // I caratteri presenti in CP1252 (accenti latini, virgolette e trattini
        // tipografici) sono mappati direttamente; quelli assenti vengono scartati
        // (//IGNORE) senza transliterazione, per non alterare gli accenti.
        $converted = @iconv('UTF-8', 'CP1252//IGNORE', $text);

        return $converted === false ? $text : $converted;
    }
}
