<?php

namespace Wonder\Plugin\Immobili\Catalog;

use Wonder\Plugin\Immobili\Models\Residenza;

/** Filtri frontend delle residenze, applicati prima della lettura delle righe. */
final class ResidenzaQuery
{
    private ResidenzaPresenter $presenter;

    public function __construct(?ResidenzaPresenter $presenter = null)
    {
        $this->presenter = $presenter ?? new ResidenzaPresenter();
    }

    /** @return array{stato: string, stato_appartamenti: string} */
    public function filters(array $input): array
    {
        return [
            'stato' => in_array($input['stato'] ?? '', ['in_costruzione', 'completata'], true)
                ? $input['stato'] : '',
            'stato_appartamenti' => in_array($input['stato_appartamenti'] ?? '', ['disponibili', 'non_disponibili'], true)
                ? $input['stato_appartamenti'] : '',
        ];
    }

    /** Criterio condiviso dal filtro e dal conteggio in Residenza::decorate(). */
    public static function availableApartmentsWhere(): string
    {
        return "`immobili`.`visible` = 'true' AND `immobili`.`deleted` = 'false' AND `immobili`.`sold` = 'false'";
    }

    public function where(array $filters, ?\DateTimeImmutable $today = null): string
    {
        $filters = $this->filters($filters);
        $today ??= new \DateTimeImmutable();
        $yearMonth = (int) $today->format('Ym');
        $clauses = ["`immobili_residenze`.`visible` = 'true'", "`immobili_residenze`.`deleted` = 'false'"];

        // Mese assente/non valido: dicembre. Anno assente: non completata.
        $completed = '(COALESCE(`fine_anno`, 0) > 0 AND '
            .'(COALESCE(`fine_anno`, 0) * 100 + CASE WHEN `fine_mese` BETWEEN 1 AND 12 '
            .'THEN `fine_mese` ELSE 12 END) < '.$yearMonth.')';

        if ($filters['stato'] === 'completata') {
            $clauses[] = $completed;
        } elseif ($filters['stato'] === 'in_costruzione') {
            $clauses[] = 'NOT '.$completed;
        }

        if ($filters['stato_appartamenti'] !== '') {
            $exists = 'EXISTS (SELECT 1 FROM `immobili` WHERE '
                .'`immobili`.`residenza_id` = `immobili_residenze`.`id` AND '
                .self::availableApartmentsWhere().')';
            $clauses[] = ($filters['stato_appartamenti'] === 'non_disponibili' ? 'NOT ' : '').$exists;
        }

        return implode(' AND ', $clauses);
    }

    /**
     * Feature GeoJSON per la mappa su TUTTE le residenze che soddisfano
     * $where. Ogni riga è mappata dal presenter, unica fonte del `geo_json`;
     * le residenze senza coordinate vengono scartate. Mirror di
     * `ImmobileQuery::geojson()`, condiviso da `pages/frontend/residenze/list.php`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function geojson(string $where): array
    {
        $cols = 'id, nome, slug, comune_id, comune_nome, indirizzo, civico, cap, '
            .'latitudine, longitudine, sold, evidence, stato, images, '
            .'inizio_anno, inizio_mese, fine_anno, fine_mese';

        $rows = Residenza::find($where, null, 'position', 'ASC', $cols);
        $features = [];

        foreach ($this->rows($rows) as $row) {
            $feature = $this->presenter->geoJson($row);

            if ($feature !== []) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    /**
     * Normalizza il risultato di `find()` a lista di righe: `find()` con limit
     * nullo torna una lista, ma una singola riga arriva come array associativo.
     *
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return isset($rows['id']) ? [$rows] : array_values($rows);
    }
}
