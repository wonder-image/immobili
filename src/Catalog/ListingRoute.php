<?php

namespace Wonder\Plugin\Immobili\Catalog;

/** Route presets shared by module lists and site overrides. */
final class ListingRoute
{
    public const PROPERTIES = [
        'immobili.list' => ['path' => '/', 'filters' => [], 'sold' => false],
        'immobili.in_vendita' => ['path' => '/in-vendita/', 'filters' => ['contratto' => 'V'], 'sold' => false],
        'immobili.in_affitto' => ['path' => '/in-affitto/', 'filters' => ['contratto' => 'A'], 'sold' => false],
        'immobili.sold' => ['path' => '/venduti/', 'filters' => ['contratto' => 'V'], 'sold' => true],
        'immobili.affittati' => ['path' => '/affittati/', 'filters' => ['contratto' => 'A'], 'sold' => true],
    ];
    public const RESIDENCES = [
        'residenze.list' => ['path' => '/', 'filters' => []],
        'residenze.in_costruzione' => ['path' => '/in-costruzione/', 'filters' => ['stato' => 'in_costruzione']],
        'residenze.realizzate' => ['path' => '/realizzate/', 'filters' => ['stato' => 'completata']],
    ];

    public static function filters(string $route, array $input): array
    {
        $preset = self::PROPERTIES[$route] ?? self::RESIDENCES[$route] ?? [];
        return array_replace($input, $preset['filters'] ?? []);
    }

    /** @return array{route:string, query:array}|null */
    public static function canonical(string $route, array $query): ?array
    {
        $target = $route;
        if ($route === 'immobili.list') {
            $contract = is_string($query['contratto'] ?? null) ? strtoupper(trim($query['contratto'])) : '';
            $target = match ($contract) {
                'V' => 'immobili.in_vendita', 'A' => 'immobili.in_affitto', default => $route,
            };
        } elseif ($route === 'residenze.list') {
            $target = match ($query['stato'] ?? '') {
                'in_costruzione' => 'residenze.in_costruzione',
                'completata' => 'residenze.realizzate', default => $route,
            };
        }
        $preset = self::PROPERTIES[$target] ?? self::RESIDENCES[$target] ?? [];
        $remaining = array_diff_key($query, $preset['filters'] ?? []);
        return $target !== $route || $remaining !== $query
            ? ['route' => $target, 'query' => $remaining] : null;
    }

    public static function redirectCanonical(string $route): void
    {
        $redirect = self::canonical($route, $_GET);
        if ($redirect === null) { return; }
        $url = __r($redirect['route']);
        if ($redirect['query'] !== []) {
            $url .= '?'.http_build_query($redirect['query'], '', '&', PHP_QUERY_RFC3986);
        }
        header('Location: '.$url, true, 301);
        exit;
    }
}
