<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\App\Support\MediaFileManager;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Impostazioni globali del modulo Immobili (singola riga, id=1).
 *
 * Contiene la configurazione della **scheda PDF**, comune a tutti i feed: logo,
 * colori e font (font/font_bold scelti dall'array `$FONT_FPDF` di
 * wonder-image/app). Gestita da `SettingsResource` (singleton).
 */
final class Settings extends Model
{
    public static string $table  = 'immobili_settings';
    public static string $folder = 'immobili/settings';
    public static string $icon   = 'bi bi-filetype-pdf';

    public static function tableSchema(): array
    {
        return [
            Column::key('pdf_logo')->length(1000)->null(),
            Column::key('pdf_color_primary')->length(10)->null(),
            Column::key('pdf_color_secondary')->length(10)->null(),
            Column::key('pdf_font')->length(100)->null(),
            Column::key('pdf_font_bold')->length(100)->null(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('pdf_logo')->file()->sanitize(false),
            Field::key('pdf_color_primary')->text()->upper(),
            Field::key('pdf_color_secondary')->text()->upper(),
            Field::key('pdf_font')->text()->sanitize(false),
            Field::key('pdf_font_bold')->text()->sanitize(false),
        ];
    }

    public static function decorate($row): array
    {
        $urls = static::storedFileUrls(
            MediaFileManager::decodeStoredFiles($row['pdf_logo'] ?? ''),
            []
        );

        $row['pdf_logo_url'] = $urls[0] ?? '';

        return $row;
    }
}
