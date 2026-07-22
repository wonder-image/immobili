<?php

/**
 * Download del Cartello immobile (PDF FPDF, backend).
 *
 * Rotta: /backend/immobili/{id}/cartello/ (permesso admin/immobili_manager).
 * Passa dalla cache con firma: rigenera solo se qualcosa è cambiato.
 */

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Pdf\PdfRenderer;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Immobile::safeFind(['slug' => $slug, 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    exit;
}

PdfRenderer::cartello($row)->download();
exit;
