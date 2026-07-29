<?php

/**
 * Download del Cartello vetrina (PDF FPDF, backend).
 *
 * Rotta: /backend/immobili/{id}/vetrina/ (permesso admin/immobili_manager).
 * Con `?sold=1` forza la fascia VENDUTO/AFFITTATO; altrimenti segue lo stato
 * `sold` dell'immobile. Passa dalla cache con firma.
 */

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Pdf\PdfRenderer;

$slug = trim((string) ($GLOBALS['ROUTE_PARAMETERS']['slug'] ?? ''));
$row = Immobile::safeFind(['slug' => $slug, 'deleted' => 'false'], 1);

if (!is_array($row) || !isset($row['id'])) {
    exit;
}

$forcedSold = immobiliIsTrue($GLOBALS['ROUTE_META']['sold'] ?? false);
$sold = $forcedSold || (
    array_key_exists('sold', $_GET)
        ? immobiliIsTrue((string) $_GET['sold'])
        : immobiliIsTrue((string) ($row['sold'] ?? ''))
);

PdfRenderer::vetrina($row, $sold)->download();
exit;
