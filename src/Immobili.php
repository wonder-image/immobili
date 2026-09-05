<?php

namespace Wonder\Plugin\Immobili;

use Wonder\App\Module\Contracts\ModuleInterface;
use Wonder\View\View;

/**
 * Entrypoint del modulo Immobili.
 *
 * Espone i path del pacchetto (view, handler, lang, asset), lo stato condiviso
 * (`context()`), e le utility di rendering (`component()`, `layout()`,
 * `renderPage()`). Ricalca le convenzioni del modulo `wonder-image/rsvp`, così
 * un sito può sovrascrivere qualunque view del modulo in
 * `custom/modules/immobili/view/…` senza toccare il pacchetto.
 */
final class Immobili implements ModuleInterface
{
    /**
     * Base della documentazione (GitBook/GitHub). Usata per i link contestuali
     * nel backend (es. guida ai cron nel pannello del feed). Personalizzabile
     * se pubblichi la doc su un altro dominio.
     */
    public const DOCS_URL = 'https://github.com/wonder-image/immobili/tree/main/docs';

    public static function root(): string
    {
        return dirname(__DIR__);
    }

    public static function manifestPath(): string
    {
        return self::root().'/module.json';
    }

    public static function httpPath(string $path): string
    {
        return self::root().'/http/'.ltrim($path, '/');
    }

    public static function handlerPath(string $path): string
    {
        return self::httpPath($path);
    }

    /**
     * Path del provider di stato (non è un handler http).
     */
    public static function contextPath(): string
    {
        return self::root().'/context.php';
    }

    /**
     * Stato condiviso del modulo (locale, feed attivi, filtri correnti, opzioni
     * tassonomie per i filtri). Caricato da `context.php` e memoizzato per la
     * richiesta.
     *
     * @return array<string, mixed>
     */
    public static function context(): array
    {
        static $cached = null;

        if ($cached === null) {
            $path = self::contextPath();
            $cached = (static function () use ($path): array {
                return require $path;
            })();
        }

        return $cached;
    }

    /**
     * Config del modulo (default di `config/module.php` + override del sito in
     * `custom/config/modules/immobili.php` + stato). Con `$key` restituisce la
     * singola voce, altrimenti l'intero array. Difensivo: durante il primo
     * setup restituisce i default senza fatal error.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public static function config(?string $key = null, mixed $default = null): mixed
    {
        $config = [];

        if (class_exists(\Wonder\App\Module\ConfigRepository::class)) {
            try {
                $config = \Wonder\App\Module\ConfigRepository::for('immobili');
            } catch (\Throwable) {
                $config = [];
            }
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? $default;
    }

    /**
     * URL pubblico di un asset del modulo (`resources/assets/<file>`), con
     * priorità all'eventuale copia pubblicata dal sito con
     * `php forge publish:module immobili --assets`. Stringa vuota se il file
     * non esiste o se il framework non supporta gli asset dei moduli (< 2.2):
     * le view possono quindi saltare il tag.
     */
    public static function asset(string $file): string
    {
        return function_exists('module_asset') ? module_asset('immobili', $file) : '';
    }

    /**
     * Emette il tag <link> per un CSS del modulo una sola volta per richiesta,
     * anche se il componente che lo usa viene incluso più volte. Nessun output
     * se l'asset non esiste (framework < 2.2 o file assente).
     */
    public static function styleOnce(string $file): void
    {
        static $emitted = [];

        if (isset($emitted[$file])) {
            return;
        }

        $emitted[$file] = true;

        $url = self::asset($file);

        if ($url !== '') {
            echo '<link rel="stylesheet" href="'.htmlspecialchars($url, ENT_QUOTES).'">';
        }
    }

    /**
     * Risolve il path di una view del modulo, dando priorità all'override del
     * sito in `custom/modules/immobili/view/<path>`.
     */
    public static function viewPath(string $path): string
    {
        $path = ltrim($path, '/');
        $root = (string) ($GLOBALS['ROOT'] ?? $_SERVER['DOCUMENT_ROOT'] ?? '');
        $override = $root !== ''
            ? $root.'/custom/modules/immobili/view/'.$path
            : '';

        if ($override !== '' && file_exists($override)) {
            return $override;
        }

        return self::root().'/view/'.$path;
    }

    /**
     * Include un componente riutilizzabile da `view/components/<name>.php`,
     * overridabile dal sito in `custom/modules/immobili/view/components/<name>.php`.
     *
     * Gli `$args` sono esposti al componente come `$args`. Le variabili globali
     * del framework (SEO, SOCIETY, PATH, STATE, …) restano disponibili.
     *
     * Esempio:
     *     <?php Immobili::component('immobili/card-base', ['immobile' => $immobile]); ?>
     */
    public static function component(string $name, array $args = []): void
    {
        $name = ltrim($name, '/');

        if (!str_ends_with($name, '.php')) {
            $name .= '.php';
        }

        $path = self::viewPath('components/'.$name);

        if (!is_file($path)) {
            return;
        }

        if (class_exists(\Wonder\App\LegacyGlobals::class)) {
            foreach (\Wonder\App\LegacyGlobals::names() as $__legacyKey) {
                if (array_key_exists($__legacyKey, $GLOBALS)) {
                    $$__legacyKey = &$GLOBALS[$__legacyKey];
                }
            }
        }
        if (array_key_exists('STATE', $GLOBALS)) {
            $STATE = &$GLOBALS['STATE'];
        }

        include $path;
    }

    /**
     * Apre un layout frontend del modulo: mappa il nome corto sul file
     * `view/layout/frontend/immobili.<name>.php` (es. `layout('main')`).
     * Passa da `viewPath()`, quindi è overridabile dal sito.
     */
    public static function layout(string $name, array $data = []): void
    {
        $name = ltrim($name, '/');

        if (str_ends_with($name, '.php')) {
            $name = substr($name, 0, -4);
        }

        View::layout(self::viewPath('layout/frontend/immobili.'.$name.'.php'), $data);
    }

    /**
     * Scaffold per pagine frontend del sito che vogliono riusare il layout e lo
     * stato del modulo. Il sito registra la route nel proprio
     * `custom/routes/route.frontend.php` puntando a un file handler che chiama
     * `Immobili::renderPage([...])`.
     *
     * Config array:
     *   - 'key'         string  chiave logica pagina → $PAGE_KEY (prefisso 'immobili.').
     *   - 'view'        string  path ASSOLUTO della view che produce il body. Obbligatorio.
     *   - 'title'       string  titolo SEO. Default: 'Immobili'.
     *   - 'description' string  description SEO. Default: ''.
     *   - 'image'       string  og:image. Default: ''.
     *   - 'url'         string  URL canonico. Default: URL corrente.
     *   - 'data'        array   variabili extra passate alla view. Default [].
     */
    public static function renderPage(array $config): void
    {
        $pageKey = trim((string) ($config['key'] ?? 'page'));
        $pageKey = str_starts_with($pageKey, 'immobili.') ? $pageKey : 'immobili.'.$pageKey;

        $viewPath = (string) ($config['view'] ?? '');
        if ($viewPath === '' || !is_file($viewPath)) {
            throw new \RuntimeException("Immobili::renderPage: 'view' obbligatorio e deve esistere ({$viewPath}).");
        }

        $state = self::context();

        $title = (string) ($config['title'] ?? 'Immobili');
        $description = (string) ($config['description'] ?? '');
        $url = (string) ($config['url'] ?? ('https://'.($_SERVER['HTTP_HOST'] ?? '').($_SERVER['REQUEST_URI'] ?? '')));
        $image = trim((string) ($config['image'] ?? ''));

        $data = is_array($config['data'] ?? null) ? $config['data'] : [];
        $data['STATE'] = $state;
        foreach ($data as $dataKey => $dataValue) {
            if (is_string($dataKey) && $dataKey !== '') {
                $GLOBALS[$dataKey] = $dataValue;
            }
        }

        $seo = $GLOBALS['SEO'] ?? (object) [];
        $seo->title = $title;
        $seo->description = $description;
        $seo->url = $url;
        $seo->breadcrumb = [];
        if ($image !== '') {
            $seo->image = $image;
        }
        $GLOBALS['SEO'] = $seo;
        $GLOBALS['PAGE_KEY'] = $pageKey;

        self::layout('main');
        View::make($viewPath, array_merge($data, ['PAGE_KEY' => $pageKey]))->render();
        View::end();
    }

    public static function langPath(): string
    {
        return self::root().'/lang/';
    }

    public static function assetPath(string $path = ''): string
    {
        return self::root().'/resources/assets/'.ltrim($path, '/');
    }
}
