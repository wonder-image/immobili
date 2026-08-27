<?php

use Wonder\Plugin\Immobili\Models\System\SyncLog;

/**
 * Download del report di un run di sincronizzazione.
 *
 * Rotta: /backend/immobili-log/{id}/download/ (permit admin|immobili_manager).
 *
 * Restituisce un JSON scaricabile con gli orari, i conteggi, l'esito e le
 * problematiche del run, più il riferimento all'artifact archiviato (e i suoi
 * metadata, se ancora presenti su disco). È sempre disponibile anche quando il
 * file grezzo del feed non esiste più.
 */

$id  = (int) ($GLOBALS['ROUTE_PARAMETERS']['id'] ?? 0);
$row = $id > 0 ? SyncLog::findById($id) : null;

if (!is_array($row) || !isset($row['id'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sincronizzazione non trovata.';
    exit;
}

$root = defined('ROOT') ? rtrim((string) ROOT, '/') : rtrim((string) getcwd(), '/');

$source   = trim((string) ($row['source'] ?? ''));
$artifact = ['source' => $source, 'exists' => false, 'path' => '', 'metadata' => null];

// Risolvi l'artifact solo se è un percorso locale sotto ROOT (niente URL remoti,
// niente path traversal): l'artifact archiviato è sempre relativo a ROOT.
if ($source !== '' && !preg_match('#^https?://#i', $source)) {
    $real = realpath($root.'/'.ltrim($source, '/'));

    if ($real !== false && str_starts_with($real, $root.'/') && is_file($real)) {
        $artifact['exists'] = true;
        $artifact['path']   = $source;

        $metaFile = dirname($real).'/metadata.json';

        if (is_file($metaFile)) {
            $decoded = json_decode((string) file_get_contents($metaFile), true);

            if (is_array($decoded)) {
                $artifact['metadata'] = $decoded;
            }
        }
    }
}

$report = [
    'id'            => (int) $row['id'],
    'data'          => (string) ($row['creation'] ?? ''),
    'provider'      => (string) ($row['provider'] ?? ''),
    'tipo'          => (string) ($row['kind'] ?? ''),
    'sorgente'      => $source,
    'immobili'      => (int) ($row['immobili_count'] ?? 0),
    'immagini'      => (int) ($row['images_count'] ?? 0),
    'esito'         => (string) ($row['status'] ?? ''),
    'problematiche' => (string) ($row['message'] ?? ''),
    'artifact'      => $artifact,
];

$filename = 'immobili-sync-'.$report['id'].'.json';
$json     = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($json));

echo $json;
exit;
