<?php

/**
 * Scheda immobile in PDF nativo (stream FPDF via Wonder\Pdf).
 *
 * Rotta: /immobili/{slug}/pdf/. Genera la scheda con il sottosistema
 * `Wonder\Plugin\Immobili\Pdf` (branding da Settings, contatti da $SOCIETY,
 * dettagli/numero immagini/header/footer configurabili in
 * custom/config/modules/immobili.php). Il PDF è prodotto in memoria e poi
 * emesso, così un eventuale errore in fase di render non corrompe lo stream.
 */

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Pdf\PdfRenderer;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Immobile::safeFind(['slug' => $slug, 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    exit;
}

PdfRenderer::scheda($row)->stream();
exit;
