<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Block;

use Wonder\Pdf;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Pdf\PdfContext;
use Wonder\Plugin\Immobili\Pdf\Support\ImageFitter;
use Wonder\Plugin\Immobili\Pdf\Support\PdfText;

/**
 * Riquadro della classe energetica: (opzionale) immagine della scala, lettera
 * della classe e valore IPE. Sostituisce le ~40 righe duplicate tra Cartello e
 * Vetrina nel progetto di riferimento.
 *
 * L'immagine della scala è un asset del modulo (`resources/assets/img/
 * classe-energetica.png`): se non è presente, il riquadro disegna solo classe e
 * IPE senza fallire. Nessun disegno se la classe è vuota.
 */
final class EnergyClassBox
{
    public static function render(
        Pdf $pdf,
        PdfContext $ctx,
        float $x,
        float $y,
        float $width,
        string $classe,
        string $ipe,
    ): void {
        $classe = trim($classe);
        if ($classe === '') {
            return;
        }

        $margin = 2.0;
        $inner = $width - ($margin * 2);
        $classHeight = 9.0;
        $ipeHeight = ($ipe !== '') ? 8.0 : 0.0;

        $scale = Immobili::assetPath('img/classe-energetica.png');
        $scaleGeom = is_file($scale)
            ? ImageFitter::contain($scale, $x + $margin, $y + $margin, $inner, 40, 'center')
            : ['x' => 0.0, 'y' => 0.0, 'w' => 0.0, 'h' => 0.0];
        $hasScale = $scaleGeom['w'] > 0.0;

        $boxHeight = $margin
            + ($hasScale ? $scaleGeom['h'] + $margin : 0.0)
            + $classHeight
            + ($ipeHeight > 0.0 ? $margin + $ipeHeight : 0.0)
            + $margin;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $width, $boxHeight, 'DF');

        $cursorY = $y + $margin;

        if ($hasScale) {
            $geom = ImageFitter::contain($scale, $x + $margin, $cursorY, $inner, 40, 'center');
            $pdf->Image($scale, $geom['x'], $geom['y'], $geom['w'], $geom['h']);
            $cursorY += $geom['h'] + $margin;
        }

        $pdf->FontBold(22);
        $pdf->SetXY($x + $margin, $cursorY);
        $pdf->MultiCell($inner, $classHeight, PdfText::encode($classe, true), 1, 'C', false);
        $cursorY += $classHeight;

        if ($ipe !== '') {
            $cursorY += $margin;
            $pdf->Rect($x + $margin, $cursorY, $inner, $ipeHeight, 'D');
            $pdf->Font(9);
            $pdf->SetXY($x + $margin, $cursorY + 0.5);
            $pdf->MultiCell($inner, 4, PdfText::encode($ipe), 0, 'C', false);
            $pdf->Font(6);
            $pdf->SetXY($x + $margin, $cursorY + 4.5);
            $pdf->MultiCell($inner, 3, PdfText::encode('kWh/mq anno'), 0, 'C', false);
        }
    }
}
