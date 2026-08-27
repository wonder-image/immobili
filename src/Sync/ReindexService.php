<?php

namespace Wonder\Plugin\Immobili\Sync;

use Wonder\Plugin\Immobili\Catalog\ImmobilePresenter;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Backfill idempotente dei campi derivati usati dalla ricerca SQL della lista
 * (`comune_nome`, `tipologia_nome`) e dello slug pubblico sui record che ne
 * sono privi.
 *
 * Serve per i record importati prima dell'introduzione delle colonne: dopo il
 * sync questi valori sono già mantenuti aggiornati. Sicuro da rieseguire.
 */
final class ReindexService
{
    /**
     * @return array{updated: int}
     */
    public function run(): array
    {
        $presenter = new ImmobilePresenter();

        $rows = Immobile::find(['deleted' => 'false']);
        $rows = is_array($rows) ? (isset($rows['id']) ? [$rows] : array_values($rows)) : [];

        $updated = 0;

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $fields = $presenter->searchFields($row);

            // Backfill dello slug per i record che ne sono privi (es. dopo la
            // migrazione dir→slug): base leggibile resa univoca, escludendo il
            // record stesso.
            if (trim((string) ($row['slug'] ?? '')) === '') {
                $base = Slug::base([
                    $fields['tipologia_nome'] ?? '',
                    $row['strada'] ?? '',
                    $row['indirizzo'] ?? '',
                    $fields['comune_nome'] ?? '',
                ]);
                $fields['slug'] = Slug::unique($base, Immobile::class, $id);
            }

            Immobile::update($fields, $id);
            $updated++;
        }

        return ['updated' => $updated];
    }
}
