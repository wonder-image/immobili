<?php

namespace Wonder\Plugin\Immobili\Catalog;

use Wonder\Plugin\Immobili\Models\Immobile;

/**
 * Ricerca/paginazione degli immobili per il frontend (lista e venduti).
 *
 * Builder SQL su singola tabella `immobili`: `where()` costruisce la condizione
 * (compatibile con `pagination()` del framework e con `Immobile::find()`/
 * `safeFind()`), `order()` l'ordinamento. I filtri operano su colonne (incluse le denormalizzate
 * `comune_nome`/`tipologia_nome`), così i conteggi della paginazione
 * sono corretti. Restituisce card già presentate e il GeoJSON per la mappa.
 * Condiviso tra `pages/frontend/list.php` e `http/api/frontend/search.php`.
 */
final class ImmobileQuery
{
    private ImmobilePresenter $presenter;

    public function __construct(?ImmobilePresenter $presenter = null)
    {
        $this->presenter = $presenter ?? new ImmobilePresenter();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, object>, total: int, pages: int, page: int, geojson: array<int, mixed>}
     */
    public function search(array $filters, int $page, int $perPage, bool $sold = false): array
    {
        $where = $this->where($filters, $sold);
        [$order, $direction] = $this->order((string) ($filters['ordina'] ?? 'recenti'));

        $total = (int) sqlCount('immobili', $where);
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $offset = ($page - 1) * $perPage;
        $rows = Immobile::safeFind($where, "{$offset}, {$perPage}", $order, $direction) ?? [];

        return [
            'items'   => $this->cards($rows),
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
            'geojson' => $this->geojson($where),
        ];
    }

    /**
     * Presenta righe DB grezze come card per la view.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, object>
     */
    public function cards(array $rows): array
    {
        return $this->presenter->cards($this->rows($rows));
    }

    /**
     * Colonna/e e direzione di ordinamento per la query SQL. `evidence` è sempre
     * primo (immobili in evidenza in cima), poi il criterio scelto.
     *
     * @return array{0: string, 1: string} [orderBy, direction]
     */
    public function order(string $ordina): array
    {
        return match ($ordina) {
            'prezzo_asc'      => ['evidence DESC, prezzo', 'ASC'],
            'prezzo_desc'     => ['evidence DESC, prezzo', 'DESC'],
            'superficie_asc'  => ['evidence DESC, superficie', 'ASC'],
            'superficie_desc' => ['evidence DESC, superficie', 'DESC'],
            default           => ['evidence DESC, id', 'DESC'],
        };
    }

    /**
     * Costruisce la condizione WHERE (stringa SQL raw) per la ricerca immobili.
     * Single-table su `immobili`. Fedele al filtraggio PHP storico. Tutti i
     * valori stringa sono escaped; i numerici castati a int.
     *
     * @param array<string, mixed> $filters
     */
    public function where(array $filters, bool $sold = false): string
    {
        $clauses = [
            "`visible` = 'true'",
            "`deleted` = 'false'",
            "`sold` = '".($sold ? 'true' : 'false')."'",
        ];

        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $like = $this->like($q);
            $columns = ['nome', 'comune_nome', 'tipologia_nome', 'strada', 'indirizzo'];
            $ors = array_map(
                static fn (string $col): string => "LOWER(`{$col}`) LIKE '%{$like}%'",
                $columns
            );
            $clauses[] = '('.implode(' OR ', $ors).')';
        }

        $comune = strtolower(trim((string) ($filters['comune'] ?? '')));
        if ($comune !== '') {
            $clauses[] = "LOWER(`comune_nome`) LIKE '%".$this->like($comune)."%'";
        }

        $tipologia = strtolower(trim((string) ($filters['tipologia'] ?? '')));
        if ($tipologia !== '') {
            $clauses[] = "LOWER(`tipologia_nome`) LIKE '%".$this->like($tipologia)."%'";
        }

        $contratto = strtoupper(trim((string) ($filters['contratto'] ?? '')));
        if ($contratto === 'A') {
            $clauses[] = "UPPER(`contratto_id`) = 'A'";
        } elseif ($contratto === 'V') {
            $clauses[] = "UPPER(`contratto_id`) <> 'A'";
        }

        if (($min = (int) ($filters['prezzo_min'] ?? 0)) > 0) {
            $clauses[] = "(UPPER(`trattativa_riservata`) = 'TRUE' OR `prezzo` = 0 OR `prezzo` >= {$min})";
        }
        if (($max = (int) ($filters['prezzo_max'] ?? 0)) > 0) {
            $clauses[] = "(UPPER(`trattativa_riservata`) = 'TRUE' OR `prezzo` = 0 OR `prezzo` <= {$max})";
        }

        if (($min = (int) ($filters['superficie_min'] ?? 0)) > 0) {
            $clauses[] = "(`superficie` = 0 OR `superficie` >= {$min})";
        }
        if (($max = (int) ($filters['superficie_max'] ?? 0)) > 0) {
            $clauses[] = "(`superficie` = 0 OR `superficie` <= {$max})";
        }

        if (($camere = (int) ($filters['camere'] ?? 0)) > 0) {
            $clauses[] = "`n_camere` >= {$camere}";
        }
        if (($bagni = (int) ($filters['bagni'] ?? 0)) > 0) {
            $clauses[] = "`n_bagni` >= {$bagni}";
        }

        return implode(' AND ', $clauses);
    }

    /**
     * Escape di un valore per una clausola LIKE: prima i metacaratteri LIKE
     * (`\`, `%`, `_`) così l'input è trattato come substring letterale, poi
     * l'escape SQL per gli apici.
     */
    private function like(string $value): string
    {
        $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);

        return $this->sqlEscape($value);
    }

    /**
     * Escape SQL. Usa la connessione mysqli del framework se disponibile;
     * altrimenti (test offline, senza runtime) ricade su addslashes.
     */
    private function sqlEscape(string $value): string
    {
        $mysqli = $GLOBALS['mysqli'] ?? null;

        return $mysqli instanceof \mysqli ? $mysqli->real_escape_string($value) : addslashes($value);
    }

    /**
     * Feature GeoJSON per la mappa su TUTTI gli immobili che soddisfano $where.
     * Query leggera: solo le colonne necessarie al marker (id, nome, prezzo,
     * superficie, stato, url, coordinate). Nessun lookup immagini: il marker
     * usa il proprio fallback grafico quando `cover` è vuota.
     *
     * @return array<int, array<string, mixed>>
     */
    public function geojson(string $where): array
    {
        $cols = 'id, nome, comune_nome, tipologia_nome, strada, prezzo, '
            .'contratto_id, trattativa_riservata, superficie, evidence, sold, '
            .'slug, latitudine, longitudine';

        $rows = Immobile::find($where, null, 'id', 'DESC', $cols);
        $features = [];

        foreach ($this->rows($rows) as $row) {
            $lat = (float) ($row['latitudine'] ?? 0);
            $lng = (float) ($row['longitudine'] ?? 0);
            if ($lat === 0.0 || $lng === 0.0) {
                continue;
            }

            $tipologia = (string) ($row['tipologia_nome'] ?? '');
            $comune = (string) ($row['comune_nome'] ?? '');
            $strada = (string) ($row['strada'] ?? '');
            $right = $strada !== '' ? $strada : $comune;
            $name = trim(($tipologia !== '' ? $tipologia : 'Immobile').($right !== '' ? ' · '.$right : ''));

            $prezzo = immobiliIsTrue($row['trattativa_riservata'] ?? '')
                ? 'Trattativa riservata'
                : immobiliFormatPrice($row['prezzo'] ?? 0);
            if ($prezzo !== '' && strtoupper((string) ($row['contratto_id'] ?? '')) === 'A'
                && !immobiliIsTrue($row['trattativa_riservata'] ?? '')) {
                $prezzo .= ' /mese';
            }

            $isSold = immobiliIsTrue($row['sold'] ?? '');
            $isFeatured = immobiliIsTrue($row['evidence'] ?? '');
            $isRent = strtoupper((string) ($row['contratto_id'] ?? '')) === 'A';
            $variant = $isSold ? 'sold' : ($isFeatured ? 'featured' : ($isRent ? 'rent' : 'default'));
            $variantLabel = match ($variant) {
                'sold' => 'Venduto',
                'featured' => 'In evidenza',
                'rent' => 'Affitto',
                default => 'Vendita',
            };

            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$lng, $lat]],
                'properties' => [
                    'id'           => (int) ($row['id'] ?? 0),
                    'name'         => $name,
                    'price'        => $prezzo,
                    'surface'      => ImmobilePresenter::formatSurface($row['superficie'] ?? 0),
                    'url'          => __r('immobile.view', ['slug' => (string) ($row['slug'] ?? '')]),
                    'cover'        => '',
                    'category'     => $tipologia,
                    'variant'      => $variant,
                    'variantLabel' => $variantLabel,
                ],
            ];
        }

        return $features;
    }

    /**
     * Valori distinti di una colonna denormalizzata (comune/tipologia) tra gli
     * immobili disponibili, per i filtri della lista. `$column` è interno
     * (mai da input utente). Ordinati alfabeticamente, case-insensitive.
     *
     * @return array<int, string>
     */
    private function findDistinctColumnValue(string $column, bool $sold = false): array
    {
        $where = "`visible` = 'true' AND `deleted` = 'false' AND `sold` = '"
            .($sold ? 'true' : 'false')."' AND `{$column}` <> ''";

        $rows = Immobile::find($where, null, $column, 'ASC', 'DISTINCT `'.$column.'`');
        $rows = is_array($rows) ? $rows : [];

        // find() con limit nullo restituisce una lista di righe; normalizziamo
        // comunque l'eventuale singola riga associativa (chiave = colonna).
        if (array_key_exists($column, $rows)) {
            $rows = [$rows];
        }

        $values = [];
        foreach ($rows as $row) {
            $value = is_array($row) ? trim((string) ($row[$column] ?? '')) : '';
            if ($value !== '') {
                $values[$value] = $value;
            }
        }

        $values = array_values($values);
        natcasesort($values);

        return array_values($values);
    }

    public function comuni(bool $sold = false): array
    {
        return $this->findDistinctColumnValue('comune_nome', $sold);
    }

    public function tipologie(bool $sold = false): array
    {
        return $this->findDistinctColumnValue('tipologia_nome', $sold);
    }

    /**
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
