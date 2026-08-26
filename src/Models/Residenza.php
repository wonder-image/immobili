<?php

namespace Wonder\Plugin\Immobili\Models;

use LogicException;
use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Residenza / cantiere gestito dall'agenzia: struttura fatta costruire o di cui
 * l'agenzia gestisce la maggior parte delle unità. Sempre creata a mano dal
 * backend (nessun feed). Gli immobili collegati puntano qui via `immobili.residenza_id`.
 */
final class Residenza extends Model
{
    public static string $table  = 'immobili_residenze';
    public static string $folder = 'residenze';
    public static string $icon   = 'bi bi-buildings';

    /** @var array<int, string> Colonne testuali libere → TEXT. */
    private const SQL_TEXT_COLUMNS = [
        'nome', 'sito_url', 'descrizione_breve', 'descrizione_lunga', 'indirizzo',
    ];

    /** @var array<int, string> Colonne file/immagine (filename JSON) → TEXT. */
    private const SQL_FILE_COLUMNS = ['logo', 'images', 'capitolato'];

    /** @var array<string, int> */
    private const SQL_VARCHAR_LENGTHS = [
        'code' => 32,
        'slug' => 191,
        'civico' => 32,
        'cap' => 16,
        'comune_nome' => 191,
        'latitudine' => 32,
        'longitudine' => 32,
        'zoom' => 8,
        'classe_energetica' => 16,
        'sold' => 5,
        'stato' => 16,
        'visible' => 5,
        'evidence' => 5,
    ];

    /** @var array<string, string> Default SQL dei flag di pubblicazione. */
    private const SQL_DEFAULTS = [
        'visible'  => 'true',
        'evidence' => 'false',
        'sold'     => 'false',
    ];

    /** @var array<string, string> FK intere verso le tassonomie canoniche. */
    private const FK_COLUMNS = [
        'comune_id' => 'immobili_comuni',
    ];

    public static function tableSchema(): array
    {
        $columns = static::sqlColumnsFromDataSchema();

        foreach ($columns as $column) {
            $name = (string) ($column->name ?? '');

            if (isset(self::SQL_DEFAULTS[$name])) {
                $column->default(self::SQL_DEFAULTS[$name]);
            }

            if (isset(self::FK_COLUMNS[$name])) {
                $column->type('INT')->length(10)->null()
                    ->foreign(self::FK_COLUMNS[$name])->foreignOnDelete('SET NULL');
                continue;
            }

            if (in_array($name, self::SQL_TEXT_COLUMNS, true)
                || in_array($name, self::SQL_FILE_COLUMNS, true)) {
                $column->type('TEXT');
                continue;
            }

            if (strtoupper((string) ($column->schema['type'] ?? 'VARCHAR')) !== 'VARCHAR') {
                continue;
            }

            $length = self::SQL_VARCHAR_LENGTHS[$name] ?? null;

            if ($length === null) {
                throw new LogicException("Lunghezza SQL non definita per immobili_residenze.{$name}");
            }

            $column->length($length);
        }

        return $columns;
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_slug'        => ['index' => 'slug'],
            'ind_visible'     => ['index' => 'visible'],
            'ind_sold'        => ['index' => 'sold'],
            'ind_position'    => ['index' => 'position'],
            'ind_comune'      => ['index' => 'comune_id'],
            'ind_comune_nome' => ['index' => 'comune_nome'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('code')->text()->uniqueCode('res_'),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('slug')->text()->slug(),

            Field::key('logo')->image()->maxSize(3)->extensions(['png'])->responsive(),
            Field::key('images')->image()->maxSize(3)->maxFile(12)->extensions(['png', 'jpg', 'jpeg'])->responsive(),
            Field::key('sito_url')->text()->sanitize(false),

            // Timeline: anno obbligatorio in UI, mese opzionale.
            Field::key('inizio_anno')->number()->decimals(0),
            Field::key('inizio_mese')->number()->decimals(0),
            Field::key('fine_anno')->number()->decimals(0),
            Field::key('fine_mese')->number()->decimals(0),

            Field::key('descrizione_breve')->text()->sanitizeFirst(),
            Field::key('descrizione_lunga')->text()->sanitizeFirst(),

            // Localizzazione: comune da tassonomia (FK), indirizzo libero.
            Field::key('indirizzo')->text()->sanitizeFirst(),
            Field::key('civico')->text(),
            Field::key('cap')->text(),
            Field::key('comune_id')->number()->decimals(0),
            Field::key('comune_nome')->text(),
            Field::key('latitudine')->text(),
            Field::key('longitudine')->text(),
            Field::key('zoom')->text(),

            Field::key('classe_energetica')->text(),
            Field::key('unita_abitative')->number()->decimals(0),
            Field::key('features')->json(),
            Field::key('capitolato')->file()->maxSize(20)->extensions(['pdf']),

            Field::key('sold')->text(),
            Field::key('stato')->text(),
            Field::key('visible')->text(),
            Field::key('evidence')->text(),
            Field::key('position')->number()->decimals(0),
        ];
    }

    public static function decorate(array $row): array
    {
        $slug = (string) ($row['slug'] ?? '');
        $row['url'] = __r('residenze.detail', ['slug' => $slug]);

        return $row;
    }
}
