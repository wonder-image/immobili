<?php

namespace Wonder\Plugin\Immobili\Sync;

/**
 * Utente API dedicato alla sincronizzazione dei feed.
 *
 * Il modulo NON usa più una variabile d'ambiente condivisa (il vecchio
 * `IMMOBILI_SYNC_TOKEN`): la sync si autentica con il meccanismo nativo del
 * framework, cioè un **utente API con token** (`api_users`). Questo servizio
 * crea/garantisce un utente `@immobili` in area `api` con authority
 * `immobili_sync` e ne espone il token JWT.
 *
 * Il token generato è lo stesso che:
 *   - gli scheduler HTTP di Getrix/immagini inviano nell'header `Authorization: Bearer …`;
 *   - Gestim (push) passa come query `?token=…` (l'endpoint lo trasforma in
 *     Bearer prima di validarlo con `Wonder\Api\Endpoint`).
 * I comandi CLI locali richiamano i servizi direttamente e non usano token.
 *
 * L'idempotenza è garantita dal controllo di esistenza: `ensure()` è sicuro da
 * chiamare a ogni render del backend feed.
 */
final class SyncApiUser
{
    /** Username dell'utente API dedicato. */
    public const USERNAME = '@immobili';

    /** Authority (area `api`) definita in config/permissions.php. */
    public const AUTHORITY = 'immobili_sync';

    /**
     * Garantisce l'esistenza dell'utente API dedicato (e del suo token) e ne
     * restituisce il record utente decorato.
     *
     * @return object utente (`infoUser`) con il token in `->{AUTHORITY}->token`
     */
    public static function ensure(): object
    {
        $user = \infoUser(self::USERNAME, 'username');

        if (!($user->exists ?? false)) {

            // La creazione con area `api` invoca la funzione dell'area
            // (`apiUser`) che genera il token JWT in `api_users`.
            \user([
                'name'                 => 'Immobili',
                'surname'              => 'Sync',
                'email'                => self::email(),
                'username'             => self::USERNAME,
                'password'             => bin2hex(random_bytes(16)),
                'authority'            => self::AUTHORITY,
                'area'                 => 'api',
                'active'               => 'true',
                'allowed_domains'      => self::allowedDomains(),
                // Al primo bootstrap SMTP potrebbe non essere configurato: il
                // token si recupera comunque dal record api_users.
                '_skip_api_token_mail' => true,
            ]);

            $user = \infoUser(self::USERNAME, 'username');
        }

        // Self-healing: utente presente ma senza token (es. api_users svuotata).
        if (($user->exists ?? false)) {

            $apiUser = \infoApiUser($user->id);

            if (!($apiUser->exists ?? false) || empty($apiUser->token ?? '')) {
                \apiUser([
                    'authority'            => self::AUTHORITY,
                    'area'                 => 'api',
                    'active'               => 'true',
                    'allowed_domains'      => self::allowedDomains(),
                    '_skip_api_token_mail' => true,
                ], [], $user, $user->id);

                $user = \infoUser($user->id);
            } else {
                // Il dominio locale/di produzione può cambiare dopo la prima
                // generazione del token. Mantiene lo stesso JWT ma riallinea
                // automaticamente i vincoli al dominio corrente.
                $expectedDomains = self::allowedDomains();
                $currentDomains = self::stringList($apiUser->allowed_domains ?? []);
                sort($expectedDomains);
                sort($currentDomains);

                if ($expectedDomains !== $currentDomains) {
                    \apiUser([
                        'authority'            => self::AUTHORITY,
                        'area'                 => 'api',
                        'active'               => (string) ($apiUser->active ?? 'true'),
                        'allowed_domains'      => $expectedDomains,
                        'allowed_ips'          => self::stringList($apiUser->allowed_ips ?? []),
                        '_skip_api_token_mail' => true,
                    ], [], $user, $user->id);

                    $user = \infoUser($user->id);
                }
            }
        }

        return $user;
    }

    /**
     * Token JWT (Bearer) dell'utente di sincronizzazione. Garantisce l'utente.
     */
    public static function token(): string
    {
        $user = self::ensure();

        if (!($user->exists ?? false)) {
            return '';
        }

        $apiUser = \infoApiUser($user->id);

        return (string) ($apiUser->token ?? '');
    }

    /**
     * Verifica time-safe che il token presentato coincida con quello attivo
     * dell'utente di sincronizzazione. Usato dagli endpoint come fallback
     * quando non si vuole passare da `Wonder\Api\Endpoint`.
     */
    public static function authorize(?string $presented): bool
    {
        $presented = trim((string) $presented);

        if ($presented === '') {
            return false;
        }

        $token = self::token();

        return $token !== '' && hash_equals($token, $presented);
    }

    /**
     * Domini ammessi per il token (dominio del sito + eventuale APP_URL).
     *
     * @return array<int, string>
     */
    private static function allowedDomains(): array
    {
        $page = $GLOBALS['PAGE'] ?? null;

        return array_values(array_filter(array_unique([
            trim((string) (is_object($page) ? ($page->domain ?? '') : '')),
            trim((string) parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST)),
        ])));
    }

    private static function email(): string
    {
        $host = trim((string) parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST));
        $domain = $host !== '' ? $host : 'localhost';

        return 'immobili-sync@'.$domain;
    }

    /** @return array<int, string> */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        } elseif (is_object($value)) {
            $value = array_values(get_object_vars($value));
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_unique(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value,
        ))));
    }
}
