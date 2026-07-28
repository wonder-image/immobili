<?php

namespace Wonder\Plugin\Immobili\Support;

/**
 * Catalogo degli attributi dell'immobile mostrabili nella scheda PDF e nella
 * scheda web. Unica fonte di verità per:
 *   - le opzioni dei repeater in `SettingsResource` (cosa si può scegliere);
 *   - le etichette (riusa le traduzioni PDF già presenti);
 *   - la risoluzione del valore da mostrare (scheda web).
 *
 * L'ordine e la selezione effettivi sono decisi in backend (Settings), con
 * fallback ai default per contesto quando non ancora configurati. Aggiungere un
 * attributo = una riga in LABELS (+ l'eventuale risoluzione in `value()`).
 */
final class AttributeCatalog
{
    /** @var array<string, string> chiave canonica => etichetta IT di default */
    private const LABELS = [
        'riferimento'      => 'Riferimento',
        'tipologia'        => 'Tipologia',
        'contratto'        => 'Contratto',
        'prezzo'           => 'Prezzo',
        'zona'             => 'Zona',
        'superficie'       => 'Superficie',
        'locali'           => 'Locali',
        'camere'           => 'Camere',
        'bagni'            => 'Bagni',
        'cucina'           => 'Cucina',
        'piano'            => 'Piano',
        'piani_edificio'   => 'Piani edificio',
        'anno_costruzione' => 'Anno di costruzione',
        'riscaldamento'    => 'Riscaldamento',
        'classe'           => 'Classe energetica',
        'ipe'              => 'I.P.E.',
        'spese'            => 'Spese condominiali',
        'posti_auto'       => 'Posti auto',
    ];

    /** @var array<string, array<int, string>> default ordinati per contesto */
    private const DEFAULTS = [
        'pdf' => [
            'riferimento', 'zona', 'contratto', 'prezzo', 'spese', 'tipologia',
            'anno_costruzione', 'piani_edificio', 'piano', 'classe', 'ipe',
            'superficie', 'locali', 'camere', 'bagni', 'cucina', 'riscaldamento', 'posti_auto',
        ],
        'scheda' => [
            'tipologia', 'contratto', 'superficie', 'locali', 'camere', 'bagni',
            'classe', 'piano', 'anno_costruzione', 'riscaldamento',
        ],
    ];

    /**
     * Opzioni per il select del repeater (chiave => etichetta), in ordine di
     * catalogo.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(self::LABELS) as $key) {
            $options[$key] = self::label($key);
        }

        return $options;
    }

    /**
     * Etichetta di un attributo. Riusa le traduzioni PDF già presenti
     * (`pages.immobili.pdf.facts.{key}`); fallback all'etichetta IT interna.
     */
    public static function label(string $key): string
    {
        if (function_exists('__t')) {
            try {
                $translated = (string) __t('pages.immobili.pdf.facts.'.$key);

                if ($translated !== '') {
                    return $translated;
                }
            } catch (\Throwable) {
                // chiave mancante: usa il fallback
            }
        }

        return self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function has(string $key): bool
    {
        return isset(self::LABELS[$key]);
    }

    /**
     * Default ordinati per contesto ('pdf' | 'scheda').
     *
     * @return array<int, string>
     */
    public static function defaults(string $context): array
    {
        return self::DEFAULTS[$context] ?? [];
    }

    /**
     * Chiavi selezionate (in ordine) da un valore repeater salvato in Settings;
     * fallback ai default del contesto quando non configurato.
     *
     * @return array<int, string>
     */
    public static function selectedKeys(mixed $stored, string $context): array
    {
        $keys = self::keysFrom($stored);

        return $keys !== [] ? $keys : self::defaults($context);
    }

    /**
     * Chiavi configurate (in ordine) da un valore repeater salvato, SENZA
     * fallback: [] quando l'admin non ha configurato nulla. Utile a chi deve
     * fare override solo se effettivamente configurato.
     *
     * Righe del repeater ([{"key":"prezzo"},…]) → lista ordinata di chiavi valide.
     *
     * @return array<int, string>
     */
    public static function keysFrom(mixed $stored): array
    {
        if (is_string($stored)) {
            $decoded = json_decode(trim($stored), true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($stored)) {
            return [];
        }

        $keys = [];

        foreach ($stored as $row) {
            $key = is_array($row) ? (string) ($row['key'] ?? reset($row)) : (string) $row;
            $key = trim($key);

            if ($key !== '' && self::has($key) && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Valore da mostrare per un attributo, risolto dall'oggetto immobile
     * presentato (scheda web). '' se non valorizzato.
     */
    public static function value(object $immobile, string $key): string
    {
        $attributi = is_array($immobile->attributi ?? null) ? $immobile->attributi : [];
        $attr = static fn (string $k): string => trim((string) ($attributi[$k] ?? ''));

        return match ($key) {
            'riferimento'      => trim((string) ($immobile->riferimento ?? $immobile->nome ?? '')),
            'tipologia'        => trim((string) ($immobile->tipologia ?? '')),
            'contratto'        => trim((string) ($immobile->contratto ?? '')),
            'prezzo'           => trim((string) ($immobile->prezzo ?? '')),
            'zona'             => trim((string) ($immobile->zona ?? $attr('zona'))),
            'superficie'       => trim((string) ($immobile->superficie ?? '')),
            'locali'           => self::positiveInt($immobile->locali ?? 0),
            'camere'           => self::positiveInt($immobile->camere ?? 0),
            'bagni'            => self::positiveInt($immobile->bagni ?? 0),
            'cucina'           => trim((string) ($immobile->cucina ?? $attr('cucina'))),
            'piano'            => trim((string) ($immobile->piano ?? $attr('piano'))),
            'piani_edificio'   => trim((string) ($immobile->numero_piani_stabile ?? $attr('piani_edificio'))),
            'anno_costruzione' => $attr('anno_costruzione'),
            'riscaldamento'    => trim((string) ($immobile->riscaldamento ?? $attr('riscaldamento'))),
            'classe'           => trim((string) ($immobile->classe ?? '')),
            'ipe'              => trim((string) ($immobile->ipe ?? $attr('ipe'))),
            'spese'            => $attr('spese'),
            'posti_auto'       => trim((string) ($immobile->posti_auto ?? $attr('posto_auto'))),
            default            => '',
        };
    }

    private static function positiveInt(mixed $value): string
    {
        return (int) $value > 0 ? (string) (int) $value : '';
    }
}
