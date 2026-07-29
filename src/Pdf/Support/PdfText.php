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
     * Porta HTML/testo editoriale a testo semplice mantenendo i paragrafi.
     */
    public static function plain(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param bool $upper se true, applica il maiuscolo (UTF-8-aware) prima della
     *                    conversione, così gli accenti maiuscoli restano corretti.
     */
    public static function encode(string $text, bool $upper = false): string
    {
        $text = self::plain($text);

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
