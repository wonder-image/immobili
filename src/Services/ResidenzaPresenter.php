<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Immobili\Models\ResidenzaImmagine;

/**
 * View-model della residenza: cover (prima immagine), URL/anteprime immagini,
 * etichetta timeline e stato derivato. Le classi utility del frontend restano
 * nelle view; qui vivono solo i dati.
 */
final class ResidenzaPresenter
{
    private const FOLDER = 'immobili/residenze';

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

    /** URL upload assoluto di un filename gallery. */
    public static function imageUrl(string $file): string
    {
        $file = trim($file);

        if ($file === '') {
            return '';
        }

        $base = rtrim((string) (($GLOBALS['PATH']->upload ?? '')), '/');

        return $base.'/'.self::FOLDER.'/'.$file;
    }

    /**
     * Anteprima webp (-620) dell'upload manuale; '' se assente.
     *
     * @param array<string, mixed> $row
     */
    public function imagePreview(array $row): string
    {
        $file = ResidenzaImmagine::firstUploadedFile($row['upload'] ?? '');

        if ($file === '') {
            return '';
        }

        $dot = strrpos($file, '.');
        $stem = $dot === false ? $file : substr($file, 0, $dot);

        return self::imageUrl($stem.'-620.webp');
    }

    /**
     * Cover = prima immagine (per position). '' se la gallery è vuota.
     *
     * @param array<string, mixed> $row
     */
    public function cover(array $row): string
    {
        foreach ($this->galleryRows($row) as $image) {
            $url = $this->imagePreview($image);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Immagini della gallery (src assoluto + alt).
     *
     * @param array<string, mixed> $row
     * @return array<int, array{src: string, alt: string}>
     */
    public function images(array $row): array
    {
        $images = [];

        foreach ($this->galleryRows($row) as $image) {
            $file = ResidenzaImmagine::firstUploadedFile($image['upload'] ?? '');

            if ($file === '') {
                continue;
            }

            $images[] = [
                'src' => self::imageUrl($file),
                'alt' => (string) ($image['titolo'] ?? ''),
            ];
        }

        return $images;
    }

    /**
     * Righe gallery ordinate per position. Richiede il DB.
     *
     * @param array<string, mixed> $row
     * @return array<int, array<string, mixed>>
     */
    private function galleryRows(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);

        if ($id <= 0) {
            return [];
        }

        $rows = ResidenzaImmagine::find(['residenza_id' => $id], null, 'position', 'ASC');

        if (is_array($rows) && isset($rows['id'])) {
            return [$rows];
        }

        return is_array($rows) ? $rows : [];
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
