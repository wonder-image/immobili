<?php

namespace Wonder\Plugin\Immobili\Support\Forms;

use Throwable;
use Wonder\Plugin\Immobili\Models\Taxonomy\Categoria;
use Wonder\Plugin\Immobili\Models\Taxonomy\Comune;
use Wonder\Plugin\Immobili\Models\Taxonomy\Macrotipologia;
use Wonder\Plugin\Immobili\Models\Taxonomy\Quartiere;
use Wonder\Plugin\Immobili\Models\Taxonomy\QuartiereZona;
use Wonder\Plugin\Immobili\Models\Taxonomy\Tipologia;
use Wonder\Plugin\Immobili\Support\Taxonomy;

/**
 * Testi, dizionari e tassonomie usati dal form backend degli immobili.
 */
final class ImmobileForm extends FormText
{
    /** @var array<string, array<string, string>> */
    private const OPTION_KEYS = [
        'category' => [
            '1' => 'residential',
            '2' => 'commercial',
            '3' => 'business',
            '4' => 'holiday',
            '5' => 'land',
        ],
        'contract' => [
            'A' => 'rent',
            'V' => 'sale',
        ],
        'contract_duration' => [
            '1' => 'four_plus_four',
            '2' => 'six_plus_six',
            '3' => 'eight_plus_eight',
            '4' => 'twelve_plus_twelve',
            '5' => 'student',
            '6' => 'company_lease',
            '7' => 'three_plus_two',
            '8' => 'seasonal',
            '9' => 'temporary',
            '255' => 'other',
        ],
        'construction_type' => [
            '1' => 'economy',
            '2' => 'standard',
            '3' => 'upper_middle',
            '4' => 'prestigious',
            '5' => 'period',
            '6' => 'railing',
            '7' => 'luxury',
            '255' => 'other',
        ],
        'construction_status' => [
            '1' => 'new',
            '2' => 'good',
            '3' => 'renovated',
            '4' => 'fair',
            '5' => 'to_renovate',
            '6' => 'excellent',
            '7' => 'discreet',
        ],
        'occupancy' => [
            '1' => 'vacant',
            '2' => 'vacant_on_deed',
            '3' => 'owner_occupied',
            '4' => 'tenant_occupied',
            '5' => 'under_construction',
            '6' => 'not_built',
        ],
        'kitchen' => [
            '1' => 'eat_in',
            '2' => 'kitchenette',
            '3' => 'small_kitchen',
            '4' => 'semi_eat_in',
            '5' => 'dining_kitchen',
            '6' => 'open_plan',
            '255' => 'absent',
        ],
        'furnishing' => [
            '1' => 'partial',
            '2' => 'complete',
            '255' => 'absent',
        ],
        'garage' => [
            '1' => 'single',
            '2' => 'double',
            '3' => 'triple',
            '255' => 'absent',
        ],
        'window_frames' => [
            '1' => 'glass_plastic',
            '2' => 'glass_wood',
            '3' => 'glass_metal',
            '4' => 'double_glass_plastic',
            '5' => 'double_glass_wood',
            '6' => 'double_glass_metal',
        ],
        'tv_system' => [
            '1' => 'centralized',
            '2' => 'individual',
            '255' => 'absent',
        ],
        'heating' => [
            '1' => 'independent',
            '2' => 'centralized',
            '255' => 'absent',
        ],
        'heating_fuel' => [
            '1' => 'methane',
            '2' => 'diesel',
            '3' => 'lpg',
            '4' => 'panels',
            '5' => 'air',
            '6' => 'wood',
            '7' => 'solar',
            '8' => 'photovoltaic',
            '9' => 'district_heating',
            '10' => 'heat_pump',
            '11' => 'electric',
            '12' => 'gas',
            '13' => 'pellet',
            '255' => 'other',
        ],
        'hot_water' => [
            '1' => 'centralized',
            '2' => 'independent',
            '255' => 'absent',
        ],
        'energy_law' => [
            '0' => 'dl_192_2005',
            '1' => 'law_90_2013',
        ],
        'status' => [
            'active' => 'active',
            'suspended' => 'suspended',
            'purchased' => 'purchased',
            'rented' => 'rented',
        ],
    ];

    /**
     * Colonne FK verso le tassonomie canoniche (INT nullable): un valore vuoto/0
     * va rimosso al salvataggio per non violare il vincolo di chiave esterna
     * (l'id 0 non esiste).
     *
     * @var array<int, string>
     */
    private const FK_TAXONOMY_COLUMNS = [
        'categoria_id', 'macrotipologia_id', 'tipologia_id',
        'comune_id', 'quartiere_id', 'quartiere_zona_id',
    ];

    /** @var array<string, array<string, mixed>> */
    private static array $taxonomyOptions = [];

    public static function text(string $key, ?string $fallback = null): string
    {
        return self::resolve('immobili', $key, $fallback);
    }

    /** @return array<string, string> */
    public static function options(string $group, bool $withBlank = true): array
    {
        $options = $withBlank ? ['' => '--'] : [];

        foreach (self::OPTION_KEYS[$group] ?? [] as $value => $key) {
            $options[(string) $value] = self::text('options.'.$group.'.'.$key);
        }

        return $options;
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function categories(): array
    {
        return self::taxonomy(Categoria::class);
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function macroTypes(): array
    {
        return self::taxonomy(Macrotipologia::class, 'categoria_id', 'categoria');
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function types(): array
    {
        return self::taxonomy(Tipologia::class, 'macrotipologia_id', 'macrotipologia');
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function municipalities(): array
    {
        return self::taxonomy(Comune::class);
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function districts(): array
    {
        return self::taxonomy(Quartiere::class, 'comune_id', 'comune');
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function districtZones(): array
    {
        return self::taxonomy(QuartiereZona::class, 'quartiere_id', 'quartiere');
    }

    /** @return array<string, string|array<string, mixed>> */
    public static function energyClasses(): array
    {
        return FormText::energyClasses();
    }

    /**
     * Nome canonico di una tassonomia dato l'id (per denormalizzare i campi
     * *_nome al salvataggio manuale). '' se l'id non è valido.
     *
     * @param class-string $model
     */
    public static function taxonomyLabel(string $model, string $id): string
    {
        return Taxonomy::nomeById($model, (int) $id);
    }

    /**
     * Elimina selezioni figlie non compatibili con il relativo parent. Oltre
     * alla cascata JavaScript, questa normalizzazione impedisce che un POST
     * costruito a mano salvi combinazioni tassonomiche incoerenti.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function normalizeDependentValues(array $values): array
    {
        if (!self::optionMatches(
            self::macroTypes(),
            (string) ($values['macrotipologia_id'] ?? ''),
            'categoria',
            (string) ($values['categoria_id'] ?? '')
        )) {
            $values['macrotipologia_id'] = '';
            $values['tipologia_id'] = '';
        }

        if (!self::optionMatches(
            self::types(),
            (string) ($values['tipologia_id'] ?? ''),
            'macrotipologia',
            (string) ($values['macrotipologia_id'] ?? '')
        )) {
            $values['tipologia_id'] = '';
        }

        if (!self::optionMatches(
            self::districts(),
            (string) ($values['quartiere_id'] ?? ''),
            'comune',
            (string) ($values['comune_id'] ?? '')
        )) {
            $values['quartiere_id'] = '';
            $values['quartiere_zona_id'] = '';
        }

        if (!self::optionMatches(
            self::districtZones(),
            (string) ($values['quartiere_zona_id'] ?? ''),
            'quartiere',
            (string) ($values['quartiere_id'] ?? '')
        )) {
            $values['quartiere_zona_id'] = '';
        }

        if (!self::optionMatches(
            self::energyClasses(),
            (string) ($values['classe_energetica'] ?? ''),
            'legge',
            (string) ($values['legge_classe_energetica_id'] ?? '')
        )) {
            $values['classe_energetica'] = '';
        }

        // Le FK tassonomia sono INT nullable: una selezione vuota/0 va rimossa
        // dai valori, così l'insert la lascia NULL e non viola il vincolo di
        // chiave esterna (l'id 0 non esiste in tabella).
        foreach (self::FK_TAXONOMY_COLUMNS as $fk) {
            if (array_key_exists($fk, $values) && (int) $values[$fk] <= 0) {
                unset($values[$fk]);
            }
        }

        return $values;
    }

    /**
     * @param class-string $model
     * @return array<string, string|array<string, mixed>>
     */
    private static function taxonomy(
        string $model,
        ?string $relationColumn = null,
        ?string $filterName = null
    ): array {
        $cacheKey = implode('|', [$model, $relationColumn ?? '', $filterName ?? '']);

        if (isset(self::$taxonomyOptions[$cacheKey])) {
            return self::$taxonomyOptions[$cacheKey];
        }

        $options = ['' => '--'];

        try {
            // Tassonomie canoniche: righe condivise da tutti i gestionali. Il
            // valore dell'opzione è l'id canonico (le colonne *_id dell'immobile
            // sono FK intere), l'etichetta è il nome.
            $rows = $model::find([]);
        } catch (Throwable) {
            return self::$taxonomyOptions[$cacheKey] = $options;
        }

        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        if (!is_array($rows)) {
            return self::$taxonomyOptions[$cacheKey] = $options;
        }

        $grouped = [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $value = (string) $row['id'];
            $label = trim((string) ($row['nome'] ?? ''));

            if ($value === '' || $label === '') {
                continue;
            }

            $grouped[$value]['label'] ??= $label;

            // La relazione col genitore è già l'id canonico (FK intera): il valore
            // del filtro è direttamente l'id del genitore, nessuna traduzione.
            if ($relationColumn !== null && $filterName !== null) {
                $parent = trim((string) ($row[$relationColumn] ?? ''));

                if ($parent !== '' && $parent !== '0') {
                    $grouped[$value]['filters'][$parent] = true;
                }
            }
        }

        uasort($grouped, static fn (array $left, array $right): int => strnatcasecmp(
            (string) ($left['label'] ?? ''),
            (string) ($right['label'] ?? '')
        ));

        foreach ($grouped as $value => $entry) {
            $label = (string) ($entry['label'] ?? $value);
            $filters = array_keys((array) ($entry['filters'] ?? []));

            $options[(string) $value] = $filterName !== null && $filters !== []
                ? self::filteredOption($label, $filterName, $filters)
                : $label;
        }

        return self::$taxonomyOptions[$cacheKey] = $options;
    }

    /**
     * @param array<string, string|array<string, mixed>> $options
     */
    private static function optionMatches(
        array $options,
        string $value,
        string $filter,
        string $parentValue
    ): bool {
        $value = trim($value);
        $parentValue = trim($parentValue);

        if ($value === '') {
            return true;
        }

        $option = $options[$value] ?? null;

        if (!is_array($option)) {
            return false;
        }

        $encoded = $option['filter'][$filter] ?? '[]';
        $allowed = is_string($encoded) ? json_decode($encoded, true) : $encoded;

        if (!is_array($allowed)) {
            return false;
        }

        return in_array($parentValue, array_map('strval', $allowed), true);
    }
}
