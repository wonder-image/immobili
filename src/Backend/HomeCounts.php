<?php

namespace Wonder\Plugin\Immobili\Backend;

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;

/**
 * Numeri del modulo per le card di stato "Immobili" e "Residenze" della home del
 * backend: immobili (totale, in vendita, in affitto, venduti) e residenze
 * (totale, visibili, in evidenza). Contano solo le righe non cancellate;
 * vendita/affitto seguono la convenzione del catalogo (`contratto_id` 'A' =
 * affitto, tutto il resto = vendita) ed escludono i venduti. Le query girano
 * una volta per richiesta, anche se le due card le chiedono entrambe.
 */
final class HomeCounts
{
    /** @var array{immobili: array<string, int>, residenze: array<string, int>}|null */
    private static ?array $counts = null;

    /**
     * @return array{immobili: array{totale: int, vendita: int, affitto: int, venduti: int}, residenze: array{totale: int, visibili: int, evidenza: int}}
     */
    public static function counts(): array
    {
        if (self::$counts !== null) {
            return self::$counts;
        }

        $alive = "`deleted` = 'false'";
        $available = $alive." AND `sold` <> 'true'";

        return self::$counts = [
            'immobili' => [
                'totale' => (int) sqlCount(Immobile::$table, $alive),
                'vendita' => (int) sqlCount(Immobile::$table, $available." AND UPPER(`contratto_id`) <> 'A'"),
                'affitto' => (int) sqlCount(Immobile::$table, $available." AND UPPER(`contratto_id`) = 'A'"),
                'venduti' => (int) sqlCount(Immobile::$table, $alive." AND `sold` = 'true'"),
            ],
            'residenze' => [
                'totale' => (int) sqlCount(Residenza::$table, $alive),
                'visibili' => (int) sqlCount(Residenza::$table, $alive." AND `visible` = 'true'"),
                'evidenza' => (int) sqlCount(Residenza::$table, $alive." AND `evidence` = 'true'"),
            ],
        ];
    }

    /** @return array{value: string, detail: string} */
    public static function immobili(array $counts): array
    {
        $immobili = (array) ($counts['immobili'] ?? []);

        return [
            'value' => (string) (int) ($immobili['totale'] ?? 0),
            'detail' => implode(' · ', [
                (int) ($immobili['vendita'] ?? 0).' in vendita',
                (int) ($immobili['affitto'] ?? 0).' in affitto',
                self::plural((int) ($immobili['venduti'] ?? 0), 'venduto', 'venduti'),
            ]),
        ];
    }

    /** @return array{value: string, detail: string} */
    public static function residenze(array $counts): array
    {
        $residenze = (array) ($counts['residenze'] ?? []);

        return [
            'value' => (string) (int) ($residenze['totale'] ?? 0),
            'detail' => implode(' · ', [
                self::plural((int) ($residenze['visibili'] ?? 0), 'visibile', 'visibili'),
                (int) ($residenze['evidenza'] ?? 0).' in evidenza',
            ]),
        ];
    }

    public static function reset(): void
    {
        self::$counts = null;
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }
}
