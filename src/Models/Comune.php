<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Comune. FK a `Regione` e `Provincia`. Include coordinate e CAP per la mappa.
 */
final class Comune extends Model
{
    public static string $table = 'immobili_comuni';
    public static string $icon  = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'provider', 'codice', 'regione_id', 'provincia_id', 'nome',
                'cap', 'cod_catastale', 'capoluogo', 'latitudine', 'longitudine',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider'  => ['index' => 'provider'],
            'ind_codice'    => ['index' => 'codice'],
            'ind_provincia' => ['index' => 'provincia_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('provider')->text(),
            Field::key('codice')->text(),
            Field::key('regione_id')->text(),
            Field::key('provincia_id')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('cap')->text(),
            Field::key('cod_catastale')->text()->upper(),
            Field::key('capoluogo')->text(),
            Field::key('latitudine')->text(),
            Field::key('longitudine')->text(),
        ];
    }
}
