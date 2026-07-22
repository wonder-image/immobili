<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf;

/**
 * Costruisce l'elenco dei dettagli (label + valore) mostrati nella scheda PDF,
 * secondo l'ordine di chiavi definito in `PdfConfig::scheda()['facts']`.
 *
 * I valori arrivano dall'oggetto presentato (campi formattati) o dalla riga
 * grezza / attributi (campi non esposti dal presenter). Le righe vuote sono
 * scartate. Le stringhe restano UTF-8: la codifica per FPDF avviene al disegno.
 */
final class PdfFacts
{
    /** @var array<string, string> */
    private const FALLBACK_LABELS = [
        'riferimento'      => 'Riferimento',
        'zona'             => 'Zona',
        'contratto'        => 'Contratto',
        'prezzo'           => 'Prezzo',
        'spese'            => 'Spese condominiali',
        'tipologia'        => 'Tipologia',
        'anno_costruzione' => 'Anno di costruzione',
        'piani_edificio'   => 'Piani edificio',
        'piano'            => 'Piano',
        'classe'           => 'Classe energetica',
        'ipe'              => 'I.P.E.',
        'superficie'       => 'Superficie',
        'locali'           => 'Locali',
        'camere'           => 'Camere',
        'bagni'            => 'Bagni',
        'cucina'           => 'Cucina',
        'riscaldamento'    => 'Riscaldamento',
        'posti_auto'       => 'Posti auto',
    ];

    /**
     * @param array<string, mixed> $row  riga grezza dell'immobile
     * @param object $presented  oggetto di ImmobilePresenter::present()
     * @param array<int, string> $keys  chiavi in ordine di visualizzazione
     * @return array<int, array{key:string, label:string, value:string}>
     */
    public static function build(array $row, object $presented, array $keys): array
    {
        $attributi = self::attributi($row);

        $facts = [];

        foreach ($keys as $key) {
            $value = self::value($key, $row, $presented, $attributi);

            if (trim($value) === '') {
                continue;
            }

            $facts[] = [
                'key'   => $key,
                'label' => self::label($key),
                'value' => $value,
            ];
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $attributi
     */
    private static function value(string $key, array $row, object $presented, array $attributi): string
    {
        return match ($key) {
            'riferimento'      => self::str($row['riferimento'] ?? '') ?: self::str($row['nome'] ?? ''),
            'zona'             => self::str($row['zona'] ?? ($attributi['zona'] ?? '')),
            'contratto'        => self::str($presented->contratto ?? ''),
            'prezzo'           => self::str($presented->prezzo ?? ''),
            'spese'            => self::str($row['spese'] ?? ($attributi['spese'] ?? '')),
            'tipologia'        => self::str($presented->tipologia ?? ''),
            'anno_costruzione' => self::str($row['anno_costruzione'] ?? ($attributi['anno_costruzione'] ?? '')),
            'piani_edificio'   => self::str($row['piani_edificio'] ?? ($attributi['piani_edificio'] ?? '')),
            'piano'            => self::str($row['piano'] ?? ($attributi['piano'] ?? '')),
            'classe'           => self::str($presented->classe ?? ''),
            'ipe'              => self::str($row['ipe'] ?? ($attributi['ipe'] ?? '')),
            'superficie'       => self::str($presented->superficie ?? ''),
            'locali'           => self::positiveInt($presented->locali ?? 0),
            'camere'           => self::positiveInt($presented->camere ?? 0),
            'bagni'            => self::positiveInt($presented->bagni ?? 0),
            'cucina'           => self::str($row['cucina'] ?? ($attributi['cucina'] ?? '')),
            'riscaldamento'    => self::str($row['riscaldamento'] ?? ($attributi['riscaldamento'] ?? '')),
            'posti_auto'       => self::str($row['n_posti_auto'] ?? ($row['posti_auto'] ?? ($attributi['posti_auto'] ?? ''))),
            default            => '',
        };
    }

    private static function label(string $key): string
    {
        // Le label passano da __t (modulo bilingua). Il framework LANCIA se la
        // chiave manca (TranslationProvider::getValue): intercettiamo l'eccezione
        // e ricadiamo sull'etichetta di default, così una chiave non tradotta
        // (es. un fact aggiunto da un sito) non fa mai fallire il PDF.
        if (function_exists('__t')) {
            try {
                $translated = (string) __t('pages.immobili.pdf.facts.'.$key);

                if ($translated !== '') {
                    return $translated;
                }
            } catch (\Throwable) {
                // chiave mancante: usa il fallback sotto
            }
        }

        return self::FALLBACK_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function attributi(array $row): array
    {
        $attributi = $row['attributi'] ?? [];

        if (is_array($attributi)) {
            return $attributi;
        }

        if (function_exists('immobiliDecodeJsonArray')) {
            return immobiliDecodeJsonArray($attributi);
        }

        return [];
    }

    private static function str(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function positiveInt(mixed $value): string
    {
        return (int) $value > 0 ? (string) (int) $value : '';
    }
}
