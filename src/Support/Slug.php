<?php

namespace Wonder\Plugin\Immobili\Support;

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Catalog\ImmobilePresenter;
use Wonder\Support\Text\Slug as TextSlug;

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
    private const MAX_LENGTH = 191;
    private const BASE_LENGTH = 180;

    /**
     * Base leggibile dello slug a partire dai pezzi (tipologia, via, comune, …).
     * `$fallback` è il valore usato quando i pezzi sono tutti vuoti: 'immobile'
     * per gli immobili, 'residenza' per le residenze.
     *
     * @param array<int, mixed> $parts
     */
    public static function base(array $parts, string $fallback = 'immobile'): string
    {
        $parts = array_map(static fn ($p): string => trim((string) $p), $parts);
        $text = trim(implode(' ', array_filter($parts)));

        // `Wonder\Support\Text\Slug::make()` normalizza in ASCII (translitterazione
        // + minuscole) usando `_` come separatore, pensato per chiavi/id. Per lo
        // slug pubblico URL-friendly convertiamo `_` in `-`; nessuna copia locale
        // della logica di slugificazione.
        $slug = str_replace('_', '-', TextSlug::make($text)) ?: $fallback;

        return self::limit($slug, self::BASE_LENGTH, $fallback);
    }

    /**
     * Base + unicità in un solo passaggio, per i reparti che partono da campi
     * grezzi invece che da una riga già formata.
     *
     * @param array<int, mixed> $parts
     */
    public static function fromParts(
        array $parts,
        string $modelClass = Immobile::class,
        int|string|null $excludeId = null,
        string $fallback = 'immobile'
    ): string {
        return self::unique(self::base($parts, $fallback), $modelClass, $excludeId, $fallback);
    }

    /**
     * Slug leggibile e univoco derivato dai campi del titolo pubblico
     * (`tipologia_nome` + `strada` `indirizzo` + `comune_nome`), così che slug
     * e titolo restino sempre coerenti. Fonte unica per creazione manuale,
     * sync feed e seeder.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row, int|string|null $excludeId = null): string
    {
        $base = self::base([ImmobilePresenter::titolo($row)]);

        return self::unique($base, Immobile::class, $excludeId);
    }

    /**
     * Rende lo slug univoco nella tabella di `$modelClass`, escludendo
     * l'eventuale riga corrente ($excludeId) così che un re-sync/update non lo
     * faccia crescere. Aggiunge `-2`, `-3`, … finché trova un valore libero.
     */
    public static function unique(
        string $base,
        string $modelClass = Immobile::class,
        int|string|null $excludeId = null,
        string $fallback = 'immobile'
    ): string {
        $base = self::limit($base !== '' ? $base : $fallback, self::MAX_LENGTH, $fallback);
        $slug = $base;
        $n = 1;

        while (self::taken($slug, $modelClass, $excludeId)) {
            $n++;
            $suffix = '-'.$n;
            $slug = self::limit($base, self::MAX_LENGTH - strlen($suffix), $fallback).$suffix;
        }

        return $slug;
    }

    private static function limit(string $slug, int $length, string $fallback): string
    {
        if (strlen($slug) <= $length) {
            return $slug;
        }

        $slug = rtrim(substr($slug, 0, $length), '-_');

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * `$modelClass` deve essere un Model del modulo con colonna `slug`. Le
     * eccezioni (DB non ancora migrato) valgono "slug libero" per allinearsi
     * alla convenzione difensiva dominante del modulo (vedi Forms, Taxonomy,
     * FeedSyncService): un fallimento di connessione durante setup non blocca.
     *
     * @param class-string $modelClass
     */
    private static function taken(string $slug, string $modelClass, int|string|null $excludeId): bool
    {
        try {
            $row = $modelClass::find(['slug' => $slug], 1);
        } catch (\Throwable) {
            return false;
        }

        if (!is_array($row) || !isset($row['id'])) {
            return false;
        }

        return $excludeId === null || (int) $row['id'] !== (int) $excludeId;
    }
}
