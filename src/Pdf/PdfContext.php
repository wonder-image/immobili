<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf;

use Wonder\Plugin\Immobili\Models\Settings;
use Wonder\Plugin\Immobili\Pdf\Support\Color;

/**
 * Contesto di branding + contatti, risolto una sola volta e condiviso dai
 * documenti. Il branding (logo, colori, font) viene dai `Settings` del modulo;
 * i contatti dai dati aziendali del sito (`$SOCIETY`).
 *
 * I documenti dipendono solo da questo VO, non dalla provenienza dei dati:
 * costruibile a mano nei test, o via `build()` a runtime.
 */
final class PdfContext
{
    public function __construct(
        public readonly Color $primary,
        public readonly Color $secondary,
        public readonly string $font,
        public readonly string $fontBold,
        public readonly string $logo,
        public readonly Contacts $contacts,
    ) {
    }

    public static function build(): self
    {
        $settings = self::settingsRow();
        $society = $GLOBALS['SOCIETY'] ?? null;

        $primary = Color::fromHex((string) ($settings['pdf_color_primary'] ?? ''), [31, 111, 235]);
        $secondary = Color::fromHex((string) ($settings['pdf_color_secondary'] ?? ''), [11, 61, 145]);
        $font = trim((string) ($settings['pdf_font'] ?? '')) ?: 'helvetica';
        $fontBold = trim((string) ($settings['pdf_font_bold'] ?? ''));
        $logo = self::selectedLogo(
            (string) ($settings['pdf_logo'] ?? ''),
            $society,
            (string) ($settings['pdf_logo_url'] ?? '')
        );

        return new self($primary, $secondary, $font, $fontBold, $logo, self::contacts($society));
    }

    private static function selectedLogo(string $selection, mixed $society, string $legacyUrl = ''): string
    {
        if (!is_object($society)) {
            return $legacyUrl;
        }

        $property = match (trim($selection) ?: 'main') {
            'main'       => 'logo',
            'black'      => 'logoBlack',
            'white'      => 'logoWhite',
            'icon'       => 'icon',
            'icon_black' => 'iconBlack',
            'icon_white' => 'iconWhite',
            default      => null,
        };

        if ($property === null) {
            return $legacyUrl;
        }

        $logo = trim((string) ($society->{$property} ?? ''));

        return $logo !== '' ? $logo : $legacyUrl;
    }

    private static function contacts(mixed $society): Contacts
    {
        if (!is_object($society)) {
            return new Contacts();
        }

        $tel = trim((string) ($society->cel ?? ''));
        if ($tel === '') {
            $tel = trim((string) ($society->tel ?? ''));
        }

        return new Contacts(
            name: (string) ($society->name ?? ''),
            tel: $tel,
            email: (string) ($society->email ?? ''),
            site: (string) ($society->site ?? ''),
            address: (string) ($society->prettyAddress ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function settingsRow(): array
    {
        if (!class_exists(Settings::class)) {
            return [];
        }

        try {
            $row = Settings::find([], 1);

            if (!is_array($row) || !isset($row['id'])) {
                return [];
            }

            return method_exists(Settings::class, 'decorate') ? Settings::decorate($row) : $row;
        } catch (\Throwable) {
            return [];
        }
    }
}
