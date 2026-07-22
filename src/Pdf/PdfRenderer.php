<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf;

use Wonder\Plugin\Immobili\Pdf\Document\CartelloImmobile;
use Wonder\Plugin\Immobili\Pdf\Document\CartelloVetrina;
use Wonder\Plugin\Immobili\Pdf\Document\SchedaImmobile;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

/**
 * Façade dei documenti PDF: unico punto di ingresso per gli handler. Costruisce
 * l'oggetto presentato, il contesto di branding/contatti e la config, poi
 * ritorna il documento su cui chiamare `stream()`, `download()` o `save()`.
 */
final class PdfRenderer
{
    /**
     * Scheda immobile (pagina pubblica). L'handler passa la riga grezza.
     *
     * @param array<string, mixed> $row
     */
    public static function scheda(array $row): SchedaImmobile
    {
        $presented = (new ImmobilePresenter())->present($row);

        return new SchedaImmobile($presented, $row, PdfContext::build(), PdfConfig::scheda());
    }

    /**
     * Cartello immobile (backend, download).
     *
     * @param array<string, mixed> $row
     */
    public static function cartello(array $row): CartelloImmobile
    {
        $presented = (new ImmobilePresenter())->present($row);

        return new CartelloImmobile($presented, $row, PdfContext::build(), PdfConfig::cartello());
    }

    /**
     * Cartello vetrina (backend, download). `$sold` sovrappone la fascia
     * VENDUTO/AFFITTATO e distingue il file in cache.
     *
     * @param array<string, mixed> $row
     */
    public static function vetrina(array $row, bool $sold = false): CartelloVetrina
    {
        $presented = (new ImmobilePresenter())->present($row);

        $config = PdfConfig::vetrina();
        $config['sold'] = $sold;

        return new CartelloVetrina($presented, $row, PdfContext::build(), $config);
    }
}
