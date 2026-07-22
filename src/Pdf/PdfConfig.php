<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf;

use Wonder\Plugin\Immobili\Immobili;

/**
 * Configurazione dei documenti PDF: default del modulo fusi con l'override del
 * sito in `custom/config/modules/immobili.php` (blocco `pdf`), letto via
 * `Immobili::config('pdf')`.
 *
 * È la superficie di personalizzazione lato sviluppatore: numero di immagini
 * della scheda, elenco ordinato dei dettagli mostrati, toggle di header/footer e
 * dei contatti sui cartelli. Nessun form backend.
 */
final class PdfConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'scheda' => [
                'images' => 6,
                'facts'  => [
                    'riferimento', 'zona', 'contratto', 'prezzo', 'spese', 'tipologia',
                    'anno_costruzione', 'piani_edificio', 'piano', 'classe', 'ipe',
                    'superficie', 'locali', 'camere', 'bagni', 'cucina', 'riscaldamento', 'posti_auto',
                ],
                'header' => ['logo' => true, 'address' => true],
                'footer' => ['tel' => true, 'email' => true, 'site' => true],
            ],
            'cartello' => ['header' => ['logo' => true], 'contacts' => ['tel' => true], 'energy' => true],
            'vetrina'  => ['header' => ['logo' => true], 'contacts' => ['tel' => true], 'energy' => true],
        ];
    }

    /**
     * Config completa (default + override sito).
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $override = [];

        if (method_exists(Immobili::class, 'config')) {
            $pdf = Immobili::config('pdf');
            if (is_array($pdf)) {
                $override = $pdf;
            }
        }

        return self::merge(self::defaults(), $override);
    }

    /**
     * @return array<string, mixed>
     */
    public static function scheda(): array
    {
        $all = self::all();

        return is_array($all['scheda'] ?? null) ? $all['scheda'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function cartello(): array
    {
        $all = self::all();

        return is_array($all['cartello'] ?? null) ? $all['cartello'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function vetrina(): array
    {
        $all = self::all();

        return is_array($all['vetrina'] ?? null) ? $all['vetrina'] : [];
    }

    /**
     * Merge profondo: gli array associativi vengono fusi ricorsivamente; gli
     * scalari e le liste (es. `facts`) vengono sostituiti integralmente
     * dall'override.
     *
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $override): array
    {
        $result = $defaults;

        foreach ($override as $key => $value) {
            if (
                isset($result[$key])
                && is_array($result[$key]) && !array_is_list($result[$key])
                && is_array($value) && !array_is_list($value)
            ) {
                $result[$key] = self::merge($result[$key], $value);
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
