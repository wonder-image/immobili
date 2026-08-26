<?php

namespace Wonder\Plugin\Immobili\Support;

use Throwable;
use Wonder\Plugin\Immobili\Models\Taxonomy\Categoria;
use Wonder\Plugin\Immobili\Models\Taxonomy\Comune;
use Wonder\Plugin\Immobili\Models\Taxonomy\Tipologia;

/**
 * Risoluzione (con cache di richiesta) delle tassonomie CANONICHE.
 *
 * Le tabelle `immobili_*` hanno una sola riga per entità reale, condivisa da
 * tutti i gestionali. Il codice nativo di un gestionale è conservato nella
 * colonna mappa `{provider}_id` (getrix_id, gestim_id, …). Questo resolver
 * traduce: codice nativo → riga/ id canonico, e id canonico → dati/nome.
 */
final class Taxonomy
{
    /** @var array<string, array<string, mixed>|null> */
    private static array $cache = [];

    /** Colonna mappa del provider (getrix_id | gestim_id | …). */
    public static function providerColumn(string $provider): string
    {
        $provider = trim($provider);

        return $provider === '' ? '' : $provider.'_id';
    }

    /**
     * Riga canonica dato il codice nativo del gestionale.
     *
     * @param class-string $model
     * @return array<string, mixed>|null
     */
    public static function byProviderCode(string $model, string $provider, string $code): ?array
    {
        $column = self::providerColumn($provider);
        $code = trim($code);

        if ($column === '' || $code === '') {
            return null;
        }

        $cacheKey = $model.'|'.$column.'|'.$code;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            $row = $model::find([$column => $code], 1);
        } catch (Throwable) {
            return self::$cache[$cacheKey] = null;
        }

        return self::$cache[$cacheKey] = (is_array($row) && isset($row['id'])) ? $row : null;
    }

    /**
     * Id canonico dato il codice nativo del gestionale (0 se non risolvibile).
     *
     * @param class-string $model
     */
    public static function idByProviderCode(string $model, string $provider, string $code): int
    {
        return (int) (self::byProviderCode($model, $provider, $code)['id'] ?? 0);
    }

    /**
     * Riga canonica per id.
     *
     * @param class-string $model
     * @return array<string, mixed>|null
     */
    public static function byId(string $model, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $cacheKey = $model.'|id|'.$id;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            $row = $model::findById($id);
        } catch (Throwable) {
            return self::$cache[$cacheKey] = null;
        }

        return self::$cache[$cacheKey] = (is_array($row) && isset($row['id'])) ? $row : null;
    }

    /** @param class-string $model */
    public static function nomeById(string $model, int $id): string
    {
        return (string) (self::byId($model, $id)['nome'] ?? '');
    }

    /**
     * Comune canonico per nome (+ opz. sigla provincia): usato dai gestionali
     * che forniscono i nomi e non i codici (es. Gestim). Match case-insensitive.
     *
     * @return array<string, mixed>|null
     */
    public static function comuneByName(string $nome, string $provinciaSigla = ''): ?array
    {
        $nome = trim($nome);

        if ($nome === '') {
            return null;
        }

        $sigla = strtoupper(trim($provinciaSigla));
        $cacheKey = Comune::class.'|nome|'.mb_strtolower($nome).'|'.$sigla;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            $rows = Comune::find(['nome' => $nome]);
        } catch (Throwable) {
            return self::$cache[$cacheKey] = null;
        }

        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        if (!is_array($rows)) {
            return self::$cache[$cacheKey] = null;
        }

        $match = null;

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            // Senza sigla provincia prende il primo; con sigla filtra la provincia.
            if ($sigla === '') {
                $match = $row;
                break;
            }

            $prov = self::byId(\Wonder\Plugin\Immobili\Models\Taxonomy\Provincia::class, (int) ($row['provincia_id'] ?? 0));

            if (is_array($prov) && strtoupper(trim((string) ($prov['sigla'] ?? ''))) === $sigla) {
                $match = $row;
                break;
            }
        }

        return self::$cache[$cacheKey] = $match;
    }

    // Convenienze usate dallo slug / dai presenter (per id canonico).

    public static function categoriaNomeById(int $id): string
    {
        return self::nomeById(Categoria::class, $id);
    }

    public static function tipologiaNomeById(int $id): string
    {
        return self::nomeById(Tipologia::class, $id);
    }

    public static function comuneNomeById(int $id): string
    {
        return self::nomeById(Comune::class, $id);
    }
}
