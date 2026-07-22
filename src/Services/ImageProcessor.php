<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Custom\Image\ResponsiveImage;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Models\SyncLog;

/**
 * Secondo piano della pipeline immagini.
 *
 * Elabora, a lotti, le immagini ancora `resized = 'false'`: scarica l'originale a
 * massima risoluzione in locale e genera le varianti responsive (webp + formati
 * di default `RESPONSIVE_IMAGE_SIZES`) tramite l'SDK del framework
 * `Wonder\Plugin\Custom\Image\ResponsiveImage`. È separato dalla sincronizzazione
 * perché gli immobili hanno molte immagini pesanti: si esegue via cron a batch.
 */
final class ImageProcessor
{
    /**
     * @return array{processed:int, failed:int, pending:int}
     */
    public function process(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));

        $path = $GLOBALS['PATH'] ?? null;
        if (!is_object($path) || empty($path->rUpload)) {
            return ['processed' => 0, 'failed' => 0, 'pending' => $this->pendingCount()];
        }

        $fsRoot = rtrim((string) $path->rUpload, '/');

        $rows = $this->rows(ImmobileImmagine::find(['resized' => 'false'], $limit, 'id', 'ASC'));

        $processed = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $source = trim((string) ($row['source_url'] ?? ''));
            $immobileId = (int) ($row['immobile_id'] ?? 0);

            if ($id <= 0 || $source === '' || $immobileId <= 0) {
                if ($id > 0) {
                    ImmobileImmagine::update(['resized' => 'true'], $id);
                }
                continue;
            }

            $relative = $this->download($fsRoot, $immobileId, (string) ($row['external_id'] ?? (string) $id), $source);

            if ($relative === null) {
                $failed++;
                // Segno come processata per non ritentare all'infinito; resta la source_url remota.
                ImmobileImmagine::update(['resized' => 'true'], $id);
                continue;
            }

            try {
                ResponsiveImage::path($fsRoot.'/'.$relative)->generate();
            } catch (\Throwable) {
                // La variante base (originale) resta comunque disponibile.
            }

            ImmobileImmagine::update([
                'file'    => $relative,
                'resized' => 'true',
            ], $id);

            $processed++;
        }

        $pending = $this->pendingCount();

        if ($processed > 0 || $failed > 0) {
            SyncLog::create([
                'feed_source_id' => 0,
                'provider'       => '',
                'kind'           => 'images',
                'source'         => 'ImageProcessor',
                'immobili_count' => 0,
                'images_count'   => $processed,
                'status'         => $failed > 0 ? 'error' : 'ok',
                'message'        => $processed.' elaborate · '.$failed.' errori · '.$pending.' in coda',
            ]);
        }

        return [
            'processed' => $processed,
            'failed'    => $failed,
            'pending'   => $pending,
        ];
    }

    public function pendingCount(): int
    {
        return count($this->rows(ImmobileImmagine::find(['resized' => 'false'])));
    }

    /**
     * Scarica l'originale in {rUpload}/immobili/{immobileId}/{name}.{ext}.
     * Ritorna il path relativo (per il DB) o null in caso di errore.
     */
    private function download(string $fsRoot, int $immobileId, string $name, string $source): ?string
    {
        $ext = strtolower(pathinfo(parse_url($source, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '', $name) ?: (string) mt_rand();
        $relativeDir = 'immobili/'.$immobileId;
        $fsDir = $fsRoot.'/'.$relativeDir;

        if (!is_dir($fsDir) && !@mkdir($fsDir, 0775, true) && !is_dir($fsDir)) {
            return null;
        }

        $relative = $relativeDir.'/'.$safeName.'.'.$ext;
        $dest = $fsRoot.'/'.$relative;

        $data = @file_get_contents($source);
        if ($data === false || $data === '') {
            return null;
        }

        if (@file_put_contents($dest, $data) === false) {
            return null;
        }

        return $relative;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return isset($rows['id']) ? [$rows] : array_values($rows);
    }
}
