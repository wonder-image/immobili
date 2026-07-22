<?php

namespace Wonder\Plugin\Immobili\Support;

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

/**
 * Generazione dello slug pubblico dell'immobile (colonna `slug`).
 *
 * Lo slug è leggibile (tipologia + indirizzo + comune) e NON contiene più
 * l'`external_id`. L'unicità è garantita da un suffisso numerico progressivo
 * (`-2`, `-3`, …) applicato solo in caso di collisione con un altro immobile.
 * Condiviso da sync (feed), creazione manuale e reindex/backfill.
 */
final class Slug
{
    /**
     * Base leggibile dello slug a partire dai pezzi (tipologia, via, comune, …).
     *
     * @param array<int, mixed> $parts
     */
    public static function base(array $parts): string
    {
        $parts = array_map(static fn ($p): string => trim((string) $p), $parts);
        $text = trim(implode(' ', array_filter($parts)));

        return ImmobilePresenter::slug($text) ?: 'immobile';
    }

    /**
     * Rende lo slug univoco nella tabella `immobili`, escludendo l'eventuale
     * riga corrente ($excludeId) così che un re-sync/update non lo faccia
     * crescere. Aggiunge `-2`, `-3`, … finché trova un valore libero.
     */
    public static function unique(string $base, int|string|null $excludeId = null): string
    {
        $base = $base !== '' ? $base : 'immobile';
        $slug = $base;
        $n = 1;

        while (self::taken($slug, $excludeId)) {
            $n++;
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    private static function taken(string $slug, int|string|null $excludeId): bool
    {
        $row = Immobile::find(['slug' => $slug], 1);

        if (!is_array($row) || !isset($row['id'])) {
            return false;
        }

        return $excludeId === null || (int) $row['id'] !== (int) $excludeId;
    }
}
