<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\UploadSchema as Field;

/**
 * Immagine della gallery di una residenza. Caricata a mano dal backend: il
 * framework genera automaticamente webp + varianti responsive all'upload.
 * La prima immagine (per `position`) funge da cover.
 */
final class ResidenzaImmagine extends Model
{
    public static string $table  = 'immobili_residenze_immagini';
    public static string $folder = 'immobili/residenze';
    public static string $icon   = 'bi bi-images';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema(['residenza_id', 'upload', 'titolo', 'position']),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_residenza' => ['index' => 'residenza_id'],
            'ind_position'  => ['index' => 'position'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('residenza_id')->number()->decimals(0),
            Field::key('upload')->image()->maxSize(3)->extensions(['png', 'jpg', 'jpeg']),
            Field::key('titolo')->text()->sanitizeFirst(),
            Field::key('position')->number()->decimals(0),
        ];
    }

    /** Legge il filename upload sia dal JSON corrente sia dal formato legacy. */
    public static function firstUploadedFile(mixed $storedFiles): string
    {
        $files = MediaFileManager::decodeStoredFiles($storedFiles);

        if (isset($files[0])) {
            return $files[0];
        }

        if (!is_string($storedFiles)) {
            return '';
        }

        $storedFiles = trim($storedFiles);

        if ($storedFiles === '') {
            return '';
        }

        $decoded = json_decode($storedFiles, true);

        if (is_string($decoded)) {
            return trim($decoded);
        }

        return json_last_error() === JSON_ERROR_NONE ? '' : $storedFiles;
    }
}
