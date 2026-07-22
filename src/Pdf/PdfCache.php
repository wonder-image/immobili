<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf;

/**
 * Cache su filesystem dei PDF generati, per-immobile, con invalidazione a firma.
 *
 * I file vivono accanto al QR dell'immobile:
 * `{PATH->rUpload}/immobili/{key}/{kind}.pdf` più un sidecar `{kind}.sig` con la
 * firma degli input. Se il file esiste e la firma coincide, si serve il PDF già
 * creato; altrimenti si rigenera (callback), si salva e si serve.
 *
 * Senza key (es. immobile senza external_id) o senza `PATH->rUpload` la cache è
 * disattivata e si genera sempre.
 */
final class PdfCache
{
    /**
     * @param callable():string $generate produce i byte del PDF
     */
    public static function remember(string $kind, string $key, string $signature, callable $generate): string
    {
        $dir = self::dir($key);

        if ($dir !== null) {
            $pdf = $dir.'/'.$kind.'.pdf';
            $sig = $dir.'/'.$kind.'.sig';

            if (is_file($pdf) && is_file($sig) && @file_get_contents($sig) === $signature) {
                $bytes = @file_get_contents($pdf);

                if (is_string($bytes) && $bytes !== '') {
                    return $bytes;
                }
            }
        }

        $bytes = $generate();

        if ($dir !== null && $bytes !== '') {
            @file_put_contents($dir.'/'.$kind.'.pdf', $bytes);
            @file_put_contents($dir.'/'.$kind.'.sig', $signature);
        }

        return $bytes;
    }

    /**
     * Elimina i PDF in cache di un immobile (tutti i `kind`). Utile alla
     * rimozione dell'immobile o per forzare la rigenerazione.
     */
    public static function forget(string $key): void
    {
        $dir = self::dir($key, create: false);

        if ($dir === null) {
            return;
        }

        foreach (glob($dir.'/*.pdf') ?: [] as $file) {
            @unlink($file);
            @unlink(substr($file, 0, -4).'.sig');
        }
    }

    private static function dir(string $key, bool $create = true): ?string
    {
        $path = $GLOBALS['PATH'] ?? null;

        if ($key === '' || !is_object($path) || empty($path->rUpload)) {
            return null;
        }

        $dir = rtrim((string) $path->rUpload, '/').'/immobili/'.$key;

        if (!is_dir($dir)) {
            if (!$create) {
                return null;
            }

            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return null;
            }
        }

        return $dir;
    }
}
