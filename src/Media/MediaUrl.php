<?php

namespace Wonder\Plugin\Immobili\Media;

use Wonder\App\Support\MediaFileManager;

/**
 * Composizione degli URL pubblici dei file caricati e delle loro varianti
 * responsive. Fonte unica per entrambi i reparti: la cartella arriva dal
 * `Model::$folder` del reparto chiamante ('immobili', 'residenze', …).
 *
 * I valori già in forma di URL assoluto (es. immagini di seed) passano
 * invariati e non hanno varianti responsive.
 */
final class MediaUrl
{
    /** Larghezza della variante usata come anteprima in card e liste. */
    public const PREVIEW_WIDTH = 620;

    /** URL pubblico di un filename dentro `$folder`. '' se il file è vuoto. */
    public static function url(string $file, string $folder): string
    {
        $file = trim($file);

        if ($file === '') {
            return '';
        }

        if (self::isAbsolute($file)) {
            return $file;
        }

        $path = $GLOBALS['PATH'] ?? null;
        $base = is_object($path) ? rtrim((string) ($path->upload ?? ''), '/') : '';

        return $base.'/'.trim($folder, '/').'/'.$file;
    }

    /** URL della variante di anteprima (-620.webp). '' se il file è vuoto. */
    public static function preview(string $file, string $folder): string
    {
        return self::variant($file, $folder, self::PREVIEW_WIDTH);
    }

    /** URL di una variante responsive webp a larghezza esplicita. */
    public static function variant(string $file, string $folder, int $width): string
    {
        $file = trim($file);

        if ($file === '' || self::isAbsolute($file)) {
            return self::url($file, $folder);
        }

        $dot = strrpos($file, '.');
        $stem = $dot === false ? $file : substr($file, 0, $dot);

        return self::url($stem.'-'.$width.'.webp', $folder);
    }

    /**
     * Primo filename di una colonna file/immagine: array JSON, array già
     * decodificato o formato legacy a stringa singola. '' se assente.
     *
     * `MediaFileManager::decodeStoredFiles()` sa leggere solo JSON (array o
     * stringa JSON-encoded): un filename legacy salvato come stringa
     * semplice (non JSON) non decodifica a nulla, quindi va gestito qui come
     * fallback — stessa logica di `ImmobileImmagine::firstUploadedFile()`.
     */
    public static function firstFile(mixed $stored): string
    {
        $files = MediaFileManager::decodeStoredFiles($stored);

        if (isset($files[0])) {
            return $files[0];
        }

        if (!is_string($stored)) {
            return '';
        }

        $stored = trim($stored);

        if ($stored === '') {
            return '';
        }

        $decoded = json_decode($stored, true);

        if (is_string($decoded)) {
            return trim($decoded);
        }

        return json_last_error() === JSON_ERROR_NONE ? '' : $stored;
    }

    private static function isAbsolute(string $file): bool
    {
        return filter_var($file, FILTER_VALIDATE_URL) !== false;
    }
}
