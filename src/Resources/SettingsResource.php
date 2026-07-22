<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Plugin\Immobili\Models\Settings;

/**
 * Impostazioni PDF globali (schermata unica, singleton).
 *
 * Il PDF è comune a tutti i feed, quindi la sua configurazione (logo, colori,
 * font) vive qui e non sul singolo feed. `font`/`font_bold` sono scelti
 * dall'array `$FONT_FPDF` di wonder-image/app.
 */
final class SettingsResource extends SingletonResource
{
    public static string $model = Settings::class;

    public static function path(): string
    {
        return 'immobili-settings';
    }

    public static function icon(): string
    {
        return 'bi bi-filetype-pdf';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'impostazioni PDF',
            'plural_label' => 'impostazioni PDF',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'pdf_logo'            => 'Logo PDF',
            'pdf_color_primary'   => 'Colore primario',
            'pdf_color_secondary' => 'Colore secondario',
            'pdf_font'            => 'Font',
            'pdf_font_bold'       => 'Font (grassetto)',
        ];
    }

    public static function formSchema(): array
    {
        $fonts = self::fpdfFonts();

        return [
            FormField::key('pdf_logo')->fileDragDrop(),
            FormField::key('pdf_color_primary')->color(),
            FormField::key('pdf_color_secondary')->color(),
            FormField::key('pdf_font')->select($fonts),
            FormField::key('pdf_font_bold')->select($fonts),
        ];
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title('Impostazioni PDF')
            ->order(40)
            ->authority(['admin', 'immobili_manager']);
    }

    /**
     * Opzioni font dalla tabella FPDF del framework (`$FONT_FPDF`).
     *
     * @return array<string, string>
     */
    private static function fpdfFonts(): array
    {
        $fonts = $GLOBALS['FONT_FPDF'] ?? null;

        if (is_array($fonts) && $fonts !== []) {
            return $fonts;
        }

        // Fallback ai font FPDF di base se l'array non è ancora caricato.
        return [
            'helvetica' => 'Arial',
            'times'     => 'Times',
            'courier'   => 'Courier',
        ];
    }
}
