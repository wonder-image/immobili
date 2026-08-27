<?php

namespace Wonder\Plugin\Immobili\Support\Forms;

use Throwable;

/**
 * Base condivisa dei form di reparto. Ospita ciò che immobili e residenze
 * usano allo stesso modo: la risoluzione dei testi (`forms.<reparto>.<key>`)
 * e le classi energetiche, che seguono la legge italiana e non dipendono dal
 * reparto.
 *
 * Le tassonomie (comuni, tipologie, …) restano su `ImmobileForm`: sono la
 * tassonomia canonica degli immobili, che le residenze riusano.
 */
abstract class FormText
{
    /**
     * Testo tradotto di un form di reparto. Difensivo: `pageSchema()` e
     * `labelSchema()` vengono letti anche prima che le traduzioni del modulo
     * siano disponibili, e in quel caso si restituisce il fallback (o la
     * chiave) invece di sollevare.
     */
    public static function resolve(string $section, string $key, ?string $fallback = null): string
    {
        $translationKey = 'forms.'.$section.'.'.$key;

        if (function_exists('__t')) {
            try {
                return (string) __t($translationKey);
            } catch (Throwable) {
                // Traduzioni non ancora caricate: si ripiega sotto.
            }
        }

        return $fallback ?? $translationKey;
    }

    /**
     * Classi energetiche selezionabili, marcate con la legge di riferimento
     * (`data-legge`) così che il form possa filtrarle in cascata.
     *
     * @return array<string, string|array<string, mixed>>
     */
    public static function energyClasses(): array
    {
        $options = ['' => '--'];

        foreach (['A+', 'A'] as $class) {
            $options[$class] = self::filteredOption($class, 'legge', ['0']);
        }

        foreach (['A4', 'A3', 'A2', 'A1'] as $class) {
            $options[$class] = self::filteredOption($class, 'legge', ['1']);
        }

        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $class) {
            $options[$class] = self::filteredOption($class, 'legge', ['0', '1']);
        }

        return $options;
    }

    /**
     * Opzione con i metadati di filtro usati dalla cascata JS del form.
     *
     * @param array<int, string> $values
     * @return array<string, mixed>
     */
    protected static function filteredOption(string $label, string $filter, array $values): array
    {
        return [
            'name' => $label,
            'filter' => [
                $filter => (string) json_encode(array_values($values), JSON_UNESCAPED_UNICODE),
            ],
        ];
    }
}
