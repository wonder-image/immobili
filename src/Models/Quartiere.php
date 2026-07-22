<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Quartiere. FK a `Comune` (con Regione/Provincia denormalizzate).
 */
final class Quartiere extends Model
{
    public static string $table = 'immobili_quartieri';
    public static string $icon  = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'provider', 'codice', 'regione_id', 'provincia_id', 'comune_id', 'nome',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider' => ['index' => 'provider'],
            'ind_codice'   => ['index' => 'codice'],
            'ind_comune'   => ['index' => 'comune_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('provider')->text(),
            Field::key('codice')->text(),
            Field::key('regione_id')->text(),
            Field::key('provincia_id')->text(),
            Field::key('comune_id')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
        ];
    }
}
