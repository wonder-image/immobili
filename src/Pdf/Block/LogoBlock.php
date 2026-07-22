<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Block;

use Wonder\Pdf;
use Wonder\Plugin\Immobili\Pdf\Support\ImageFitter;

/**
 * Disegna il logo dell'agenzia dentro un riquadro, scalato e allineato. Se il
 * logo non è impostato o non è leggibile, non disegna nulla.
 */
final class LogoBlock
{
    public static function render(
        Pdf $pdf,
        string $logo,
        float $x,
        float $y,
        float $maxW,
        float $maxH,
        string $align = 'left',
    ): void {
        $geom = ImageFitter::contain($logo, $x, $y, $maxW, $maxH, $align);

        if ($geom['w'] <= 0.0) {
            return;
        }

        $pdf->Image($logo, $geom['x'], $geom['y'], $geom['w'], $geom['h']);
    }
}
