<?php

namespace Wonder\Plugin\Immobili\Feed;

use Wonder\Plugin\Immobili\Feed\Contracts\FeedProvider;

/**
 * Registro dei provider/gestionali disponibili.
 *
 * I provider di default (Getrix, Gestim) sono registrati al boot del modulo da
 * `config/module.php` via `registerDefaults()`. Un sito o un altro modulo può
 * aggiungere provider custom chiamando `ProviderRegistry::register(...)`.
 */
final class ProviderRegistry
{
    /** @var array<string, FeedProvider> */
    private static array $providers = [];

    public static function register(FeedProvider $provider): void
    {
        self::$providers[$provider->key()] = $provider;
    }

    public static function registerDefaults(): void
    {
        if (self::$providers !== []) {
            return;
        }

        self::register(new GetrixProvider());
        self::register(new GestimProvider());
    }

    public static function get(string $key): ?FeedProvider
    {
        return self::$providers[trim($key)] ?? null;
    }

    public static function has(string $key): bool
    {
        return isset(self::$providers[trim($key)]);
    }

    /** @return array<string, FeedProvider> */
    public static function all(): array
    {
        return self::$providers;
    }

    /**
     * Opzioni per una select provider (key => label).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        self::registerDefaults();

        $options = [];

        foreach (self::$providers as $key => $provider) {
            $options[$key] = $provider->label();
        }

        return $options;
    }
}
