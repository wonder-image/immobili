<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Immobili\Models\Immobile;

/**
 * Ricerca/paginazione degli immobili per il frontend (lista e venduti).
 *
 * I filtri sono applicati in PHP sui record visibili: per i volumi tipici di un
 * sito immobiliare (centinaia di annunci) è semplice, sicuro (niente SQL da input
 * utente) e provider-agnostico. Restituisce card già presentate e il GeoJSON per
 * la mappa. Condiviso tra `pages/frontend/list.php` e `http/api/frontend/search.php`.
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
        $rows = $this->rows(Immobile::find([
            'visible' => 'true',
            'sold'    => $sold ? 'true' : 'false',
            'deleted' => 'false',
        ]));

        $cards = $this->presenter->cards($rows);
        $cards = array_values(array_filter($cards, fn (object $c): bool => $this->matches($c, $filters)));
        $cards = $this->sort($cards, (string) ($filters['ordina'] ?? 'recenti'));

        $total = count($cards);
        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $items = array_slice($cards, ($page - 1) * $perPage, $perPage);

        $geojson = [];
        foreach ($cards as $card) {
            if (!empty($card->geo_json)) {
                $geojson[] = $card->geo_json;
            }
        }

        return [
            'items'   => $items,
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
            'geojson' => $geojson,
        ];
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
            'superficie_desc' => ['evidence DESC, superficie', 'DESC'],
            default           => ['evidence DESC, id', 'DESC'],
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function matches(object $card, array $filters): bool
    {
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        if ($q !== '') {
            $haystack = strtolower(implode(' ', [
                $card->nome, $card->tipologia, $card->comune, $card->prettyAddress,
            ]));
            if (!str_contains($haystack, $q)) {
                return false;
            }
        }

        $comune = strtolower(trim((string) ($filters['comune'] ?? '')));
        if ($comune !== '' && !str_contains(strtolower($card->comune), $comune)) {
            return false;
        }

        $contratto = strtoupper(trim((string) ($filters['contratto'] ?? '')));
        if ($contratto !== '') {
            $cardContratto = $card->contratto === 'Affitto' ? 'A' : 'V';
            if ($cardContratto !== $contratto) {
                return false;
            }
        }

        $tipologia = strtolower(trim((string) ($filters['tipologia'] ?? '')));
        if ($tipologia !== '' && !str_contains(strtolower($card->tipologia), $tipologia)) {
            return false;
        }

        $prezzo = $this->amount($card->prezzo);
        if (($min = (int) ($filters['prezzo_min'] ?? 0)) > 0 && $prezzo > 0 && $prezzo < $min) {
            return false;
        }
        if (($max = (int) ($filters['prezzo_max'] ?? 0)) > 0 && $prezzo > 0 && $prezzo > $max) {
            return false;
        }

        $superficie = $this->amount($card->superficie);
        if (($min = (int) ($filters['superficie_min'] ?? 0)) > 0 && $superficie > 0 && $superficie < $min) {
            return false;
        }
        if (($max = (int) ($filters['superficie_max'] ?? 0)) > 0 && $superficie > 0 && $superficie > $max) {
            return false;
        }

        if (($camere = (int) ($filters['camere'] ?? 0)) > 0 && $card->camere < $camere) {
            return false;
        }
        if (($bagni = (int) ($filters['bagni'] ?? 0)) > 0 && $card->bagni < $bagni) {
            return false;
        }

        return true;
    }

    /**
     * Comuni distinti degli immobili disponibili (per il filtro della lista).
     * Ordinati alfabeticamente, case-insensitive.
     *
     * @return array<int, string>
     */
    public function comuni(bool $sold = false): array
    {
        $rows = $this->rows(Immobile::find([
            'visible' => 'true',
            'sold'    => $sold ? 'true' : 'false',
            'deleted' => 'false',
        ]));

        $comuni = [];
        foreach ($rows as $row) {
            $nome = trim($this->presenter->comuneName($row));
            if ($nome !== '') {
                $comuni[$nome] = $nome;
            }
        }

        $comuni = array_values($comuni);
        natcasesort($comuni);

        return array_values($comuni);
    }

    /**
     * @param array<int, object> $cards
     * @return array<int, object>
     */
    private function sort(array $cards, string $ordina): array
    {
        $secondary = match ($ordina) {
            'prezzo_asc'  => fn (object $a, object $b): int => $this->amount($a->prezzo) <=> $this->amount($b->prezzo),
            'prezzo_desc' => fn (object $a, object $b): int => $this->amount($b->prezzo) <=> $this->amount($a->prezzo),
            'superficie_desc' => fn (object $a, object $b): int => $this->amount($b->superficie) <=> $this->amount($a->superficie),
            default => fn (object $a, object $b): int => ($b->id <=> $a->id),
        };

        // Gli immobili in evidenza vengono sempre prima, poi si applica
        // l'ordinamento scelto.
        usort($cards, function (object $a, object $b) use ($secondary): int {
            $ev = (($b->evidence ? 1 : 0) <=> ($a->evidence ? 1 : 0));

            return $ev !== 0 ? $ev : $secondary($a, $b);
        });

        return $cards;
    }

    private function amount(string $value): int
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? 0 : (int) $digits;
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
