<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Block;

use Wonder\Pdf;

/**
 * Disegna il QR code dell'immobile (quadrato). Nessun disegno se il QR non è
 * disponibile o non leggibile.
 */
final class QrBlock
{
    public static function render(Pdf $pdf, string $qr, float $x, float $y, float $size): void
    {
        if ($qr === '' || @getimagesize($qr) === false) {
            return;
        }

        $pdf->Image($qr, $x, $y, $size, $size);
    }
}
