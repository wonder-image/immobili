<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Zona di un quartiere (livello geografico più fine). FK a `Quartiere`.
 */
final class QuartiereZona extends Model
{
    public static string $table = 'immobili_quartieri_zone';
    public static string $icon  = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'provider', 'codice', 'regione_id', 'provincia_id',
                'comune_id', 'quartiere_id', 'nome',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider'  => ['index' => 'provider'],
            'ind_codice'    => ['index' => 'codice'],
            'ind_quartiere' => ['index' => 'quartiere_id'],
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
            Field::key('quartiere_id')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
        ];
    }
}
