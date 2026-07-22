<?php

namespace Wonder\Plugin\Immobili\Support;

use Wonder\Plugin\Immobili\Models\Categoria;
use Wonder\Plugin\Immobili\Models\Comune;
use Wonder\Plugin\Immobili\Models\Tipologia;

/**
 * Risoluzione (con cache di richiesta) delle tassonomie scoping per provider.
 *
 * Le tabelle `immobili_*` sono uniche per tutti i gestionali: la coppia
 * (`provider`, `codice`) identifica la riga nativa. Usato sia in fase di
 * sincronizzazione (per costruire lo slug) sia in lettura (etichette).
 */
final class Taxonomy
{
    /** @var array<string, array<string, mixed>|null> */
    private static array $cache = [];

    /**
     * @param class-string $model
     * @return array<string, mixed>|null
     */
    public static function resolve(string $model, string $provider, string $codice): ?array
    {
        $provider = trim($provider);
        $codice = trim($codice);

        if ($provider === '' || $codice === '') {
            return null;
        }

        $cacheKey = $model.'|'.$provider.'|'.$codice;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $row = $model::find(['provider' => $provider, 'codice' => $codice], 1);
        $resolved = (is_array($row) && isset($row['id'])) ? $row : null;

        return self::$cache[$cacheKey] = $resolved;
    }

    public static function categoriaNome(string $provider, string $codice): string
    {
        return (string) (self::resolve(Categoria::class, $provider, $codice)['nome'] ?? '');
    }

    public static function tipologiaNome(string $provider, string $codice): string
    {
        return (string) (self::resolve(Tipologia::class, $provider, $codice)['nome'] ?? '');
    }

    /** @return array<string, mixed>|null */
    public static function comune(string $provider, string $codice): ?array
    {
        return self::resolve(Comune::class, $provider, $codice);
    }

    public static function comuneNome(string $provider, string $codice): string
    {
        return (string) (self::comune($provider, $codice)['nome'] ?? '');
    }
}
