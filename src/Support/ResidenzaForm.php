<?php

namespace Wonder\Plugin\Immobili\Support;

use Throwable;
use Wonder\Plugin\Immobili\Models\Comune;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;

/**
 * Testi, catalogo features e opzioni del form backend delle residenze.
 * Riusa le tassonomie/energia di ImmobileForm e la slugificazione di Slug.
 */
final class ResidenzaForm
{
    /** @var array<string, string> id feature → chiave lang (suffisso). */
    public const FEATURE_KEYS = [
        'ascensore'        => 'ascensore',
        'giardino'         => 'giardino',
        'box_auto'         => 'box_auto',
        'domotica'         => 'domotica',
        'fotovoltaico'     => 'fotovoltaico',
        'climatizzazione'  => 'climatizzazione',
        'area_verde'       => 'area_verde',
        'videosorveglianza'=> 'videosorveglianza',
        'cantina'          => 'cantina',
        'terrazzo'         => 'terrazzo',
    ];

    /** @var array<string, string> id feature → icona Bootstrap. */
    public const FEATURE_ICONS = [
        'ascensore'         => 'bi bi-arrow-down-up',
        'giardino'          => 'bi bi-tree',
        'box_auto'          => 'bi bi-car-front',
        'domotica'          => 'bi bi-house-gear',
        'fotovoltaico'      => 'bi bi-sun',
        'climatizzazione'   => 'bi bi-snow',
        'area_verde'        => 'bi bi-flower1',
        'videosorveglianza' => 'bi bi-camera-video',
        'cantina'           => 'bi bi-box2',
        'terrazzo'          => 'bi bi-brightness-high',
    ];

    public static function text(string $key, ?string $fallback = null): string
    {
        $translationKey = 'forms.residenze.'.$key;

        if (function_exists('__t')) {
            try {
                return (string) __t($translationKey);
            } catch (Throwable) {
                // pageSchema()/labelSchema() sono letti anche prima che le
                // traduzioni del modulo siano disponibili.
            }
        }

        return $fallback ?? $translationKey;
    }

    /** @return array<string, string> id → label tradotta */
    public static function features(): array
    {
        $options = [];

        foreach (self::FEATURE_KEYS as $id => $key) {
            $options[$id] = self::text('features.'.$key);
        }

        return $options;
    }

    public static function featureIcon(string $id): string
    {
        return self::FEATURE_ICONS[$id] ?? '';
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function energyClasses(): array
    {
        return ImmobileForm::energyClasses();
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function municipalities(): array
    {
        return ImmobileForm::municipalities();
    }

    public static function comuneNome(string $comuneId): string
    {
        return ImmobileForm::taxonomyLabel(Comune::class, $comuneId);
    }

    /**
     * Opzioni per il multiselect "Immobili collegati": tutti gli immobili,
     * etichettati con nome + comune. `['' => '--']` se il DB non è disponibile.
     *
     * @return array<string, string>
     */
    public static function immobili(): array
    {
        $options = [];

        try {
            $rows = Immobile::find([]);
        } catch (Throwable) {
            return $options;
        }

        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        if (!is_array($rows)) {
            return $options;
        }

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $id = (string) $row['id'];
            $nome = trim((string) ($row['nome'] ?? ''));
            $comune = trim((string) ($row['comune_nome'] ?? ''));
            $label = $nome !== '' ? $nome : ('#'.$id);

            if ($comune !== '') {
                $label .= ' — '.$comune;
            }

            $options[$id] = $label;
        }

        return $options;
    }

    /**
     * Slug leggibile e univoco nella tabella immobili_residenze. Riusa la base
     * slug generica; l'unicità è verificata contro le residenze (suffisso -2, -3…).
     */
    public static function uniqueSlug(string $nome, int|string|null $excludeId = null): string
    {
        $base = Slug::base([$nome]);
        $base = $base !== '' ? $base : 'residenza';
        $slug = $base;
        $n = 1;

        while (self::slugTaken($slug, $excludeId)) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private static function slugTaken(string $slug, int|string|null $excludeId): bool
    {
        try {
            $row = Residenza::find(['slug' => $slug], 1);
        } catch (Throwable) {
            return false;
        }

        if (!is_array($row) || !isset($row['id'])) {
            return false;
        }

        return $excludeId === null || (int) $row['id'] !== (int) $excludeId;
    }
}
