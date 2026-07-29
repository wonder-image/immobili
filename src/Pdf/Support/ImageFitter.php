<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Support;

/**
 * Calcola la geometria di un'immagine scalata dentro un riquadro mantenendo le
 * proporzioni (contain) e centrandola. Isola il calcolo ripetuto più volte nel
 * progetto di riferimento (logo, copertina, galleria).
 *
 * `fit()` è pura matematica; `contain()` legge le dimensioni reali del file con
 * `getimagesize()` e ritorna la posizione assoluta pronta per `FPDF::Image()`.
 */
final class ImageFitter
{
    /**
     * Risolve una sorgente immagine in un file locale leggibile da FPDF.
     *
     * Il presenter espone URL pubblici (per esempio
     * https://site.test/assets/upload/immobili/...), mentre FPDF e
     * getimagesize() lavorano in modo affidabile sul filesystem. La conversione
     * usa prima la coppia PATH->upload/PATH->rUpload e poi la document root.
     */
    public static function resolve(string $source): string
    {
        $source = trim($source);

        if ($source === '') {
            return '';
        }

        if (is_file($source)) {
            return $source;
        }

        $urlPath = parse_url($source, PHP_URL_PATH);
        if (!is_string($urlPath) || $urlPath === '') {
            return '';
        }

        $urlPath = rawurldecode($urlPath);
        $candidates = [];
        $path = $GLOBALS['PATH'] ?? null;

        if (is_object($path)) {
            $uploadUrlPath = parse_url((string) ($path->upload ?? ''), PHP_URL_PATH);
            $uploadRoot = rtrim((string) ($path->rUpload ?? ''), '/');

            if (
                is_string($uploadUrlPath) && $uploadUrlPath !== ''
                && $uploadRoot !== ''
                && ($urlPath === $uploadUrlPath || str_starts_with($urlPath, rtrim($uploadUrlPath, '/').'/'))
            ) {
                $relative = ltrim(substr($urlPath, strlen(rtrim($uploadUrlPath, '/'))), '/');
                $candidates[] = $uploadRoot.($relative !== '' ? '/'.$relative : '');
            }
        }

        $root = rtrim((string) ($GLOBALS['ROOT'] ?? ''), '/');
        if ($root !== '') {
            $candidates[] = $root.'/'.ltrim($urlPath, '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Dimensioni di un'immagine `srcW × srcH` scalata per stare dentro
     * `maxW × maxH` (tocca almeno un lato). Ritorna 0 se la sorgente è degenere.
     *
     * @return array{w:float,h:float}
     */
    public static function fit(int $srcW, int $srcH, float $maxW, float $maxH): array
    {
        if ($srcW <= 0 || $srcH <= 0) {
            return ['w' => 0.0, 'h' => 0.0];
        }

        $scale = min($maxW / $srcW, $maxH / $srcH);

        return ['w' => $srcW * $scale, 'h' => $srcH * $scale];
    }

    /**
     * Geometria assoluta dell'immagine `$file` contenuta nel riquadro
     * `($x, $y, $maxW, $maxH)`. Verticalmente sempre centrata; orizzontalmente
     * secondo `$align` ('left' | 'center' | 'right'). Geometria vuota se il file
     * non esiste o non è un'immagine leggibile — così il chiamante salta il disegno.
     *
     * @return array{x:float,y:float,w:float,h:float}
     */
    public static function contain(string $file, float $x, float $y, float $maxW, float $maxH, string $align = 'center'): array
    {
        $empty = ['x' => 0.0, 'y' => 0.0, 'w' => 0.0, 'h' => 0.0];

        if ($file === '' || !is_file($file)) {
            return $empty;
        }

        $size = @getimagesize($file);
        if ($size === false || (int) $size[0] <= 0 || (int) $size[1] <= 0) {
            return $empty;
        }

        $fit = self::fit((int) $size[0], (int) $size[1], $maxW, $maxH);

        $offsetX = match ($align) {
            'left' => 0.0,
            'right' => $maxW - $fit['w'],
            default => ($maxW - $fit['w']) / 2,
        };
        $offsetY = ($maxH - $fit['h']) / 2;

        return [
            'x' => $x + $offsetX,
            'y' => $y + $offsetY,
            'w' => $fit['w'],
            'h' => $fit['h'],
        ];
    }
}
