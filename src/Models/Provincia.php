<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Provincia. FK a `Regione`.
 */
final class Provincia extends Model
{
    public static string $table = 'immobili_province';
    public static string $icon  = 'bi bi-geo';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema(['provider', 'codice', 'regione_id', 'nome', 'sigla']),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider' => ['index' => 'provider'],
            'ind_codice'   => ['index' => 'codice'],
            'ind_regione'  => ['index' => 'regione_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('provider')->text(),
            Field::key('codice')->text(),
            Field::key('regione_id')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('sigla')->text()->upper(),
        ];
    }
}
