<?php

namespace Wonder\Plugin\Immobili\Models\System;

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

            // Attributi mostrati sul PDF e nella scheda web: liste ordinate di
            // chiavi (righe repeater), decodificate via AttributeCatalog.
            Column::key('pdf_facts')->json()->null(),
            Column::key('scheda_facts')->json()->null(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('pdf_logo')->text()->sanitize(false),
            Field::key('pdf_color_primary')->text()->upper(),
            Field::key('pdf_color_secondary')->text()->upper(),
            Field::key('pdf_font')->text()->sanitize(false),
            Field::key('pdf_font_bold')->text()->sanitize(false),

            Field::key('pdf_facts')->json(),
            Field::key('scheda_facts')->json(),
        ];
    }

    public static function decorate($row): array
    {
        // Compatibilità con i record creati quando pdf_logo era un upload
        // autonomo del modulo. Le nuove righe salvano invece la chiave della
        // variante configurata in Media → Logo.
        $urls = static::storedFileUrls(
            MediaFileManager::decodeStoredFiles($row['pdf_logo'] ?? ''),
            []
        );

        $row['pdf_logo_url'] = $urls[0] ?? '';

        return $row;
    }
}
