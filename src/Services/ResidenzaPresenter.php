<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\App\Support\MediaFileManager;

/**
 * View-model della residenza: cover (prima immagine), URL/anteprime immagini,
 * etichetta timeline e stato derivato. Le immagini vivono nella colonna JSON
 * `images` della residenza (array di filename), non più in una tabella figlia.
 * Le classi utility del frontend restano nelle view; qui vivono solo i dati.
 */
final class ResidenzaPresenter
{
    private const FOLDER = 'residenze';

    /** Etichetta timeline: "" se anno assente, "2025" o "03/2025". */
    public static function timelineLabel(?int $anno, ?int $mese): string
    {
        $anno = (int) $anno;

        if ($anno <= 0) {
            return '';
        }

        $mese = (int) $mese;

        if ($mese >= 1 && $mese <= 12) {
            return sprintf('%02d/%d', $mese, $anno);
        }

        return (string) $anno;
    }

    /**
     * Stato della residenza: venduto | in_arrivo | in_corso | completato.
     *
     * @param array<string, mixed> $row
     */
    public static function stato(array $row, ?int $todayYear = null, ?int $todayMonth = null): string
    {
        if (self::isTrue($row['sold'] ?? '')) {
            return 'venduto';
        }

        $override = strtolower(trim((string) ($row['stato'] ?? '')));

        if (in_array($override, ['in_arrivo', 'in_corso', 'completato'], true)) {
            return $override;
        }

        $todayYear ??= (int) date('Y');
        $todayMonth ??= (int) date('n');
        $today = $todayYear * 100 + $todayMonth;

        $start = self::yearMonth($row['inizio_anno'] ?? null, $row['inizio_mese'] ?? null, 1);
        $end = self::yearMonth($row['fine_anno'] ?? null, $row['fine_mese'] ?? null, 12);

        if ($start !== null && $today < $start) {
            return 'in_arrivo';
        }

        if ($end !== null && $today > $end) {
            return 'completato';
        }

        return 'in_corso';
    }

    /** URL upload assoluto di un filename della cartella residenze. */
    public static function imageUrl(string $file): string
    {
        $file = trim($file);

        if ($file === '') {
            return '';
        }

        // Valore già URL assoluto (es. immagini di seed): usalo così com'è.
        if (filter_var($file, FILTER_VALIDATE_URL) !== false) {
            return $file;
        }

        $base = rtrim((string) (($GLOBALS['PATH']->upload ?? '')), '/');

        return $base.'/'.self::FOLDER.'/'.$file;
    }

    /**
     * Primo filename di una colonna file/immagine (JSON array, array già
     * decodificato o formato legacy stringa). '' se assente.
     */
    public static function firstFile(mixed $stored): string
    {
        $files = MediaFileManager::decodeStoredFiles($stored);

        return $files[0] ?? '';
    }

    /** URL della variante webp responsive -620 di un filename; '' se vuoto. */
    public static function previewUrl(string $file): string
    {
        $file = trim($file);

        if ($file === '') {
            return '';
        }

        // Gli URL assoluti (seed) non hanno varianti responsive: usali diretti.
        if (filter_var($file, FILTER_VALIDATE_URL) !== false) {
            return $file;
        }

        $dot = strrpos($file, '.');
        $stem = $dot === false ? $file : substr($file, 0, $dot);

        return self::imageUrl($stem.'-620.webp');
    }

    /**
     * Cover = anteprima della prima immagine della gallery. '' se vuota.
     *
     * @param array<string, mixed> $row
     */
    public function cover(array $row): string
    {
        foreach ($this->files($row) as $file) {
            $url = self::previewUrl($file);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Immagini della gallery (src assoluto + alt), lette dalla colonna JSON.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{src: string, alt: string}>
     */
    public function images(array $row): array
    {
        $alt = (string) ($row['nome'] ?? '');
        $images = [];

        foreach ($this->files($row) as $file) {
            $src = self::imageUrl($file);

            if ($src === '') {
                continue;
            }

            $images[] = ['src' => $src, 'alt' => $alt];
        }

        return $images;
    }

    /**
     * Filename della gallery decodificati dalla colonna JSON `images`.
     *
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function files(array $row): array
    {
        return MediaFileManager::decodeStoredFiles($row['images'] ?? []);
    }

    private static function yearMonth(mixed $anno, mixed $mese, int $defaultMonth): ?int
    {
        $anno = (int) $anno;

        if ($anno <= 0) {
            return null;
        }

        $mese = (int) $mese;

        if ($mese < 1 || $mese > 12) {
            $mese = $defaultMonth;
        }

        return $anno * 100 + $mese;
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'si', 'sì', 'yes'], true);
    }
}
