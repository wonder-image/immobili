<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Block;

use Wonder\Pdf;
use Wonder\Plugin\Immobili\Pdf\Support\ImageFitter;

/**
 * Disegna il QR code dell'immobile (quadrato). Nessun disegno se il QR non è
 * disponibile o non leggibile.
 */
final class QrBlock
{
    public static function render(Pdf $pdf, string $qr, float $x, float $y, float $size): void
    {
        $file = ImageFitter::resolve($qr);

        if ($file === '' || @getimagesize($file) === false) {
            return;
        }

        $pdf->Image($file, $x, $y, $size, $size);
    }
}
