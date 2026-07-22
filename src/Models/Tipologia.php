<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Tipologia specifica dell'immobile (Appartamento, Villa, Negozio, …).
 * FK a `Categoria` e `Macrotipologia`.
 */
final class Tipologia extends Model
{
    public static string $table = 'immobili_tipologie';
    public static string $icon  = 'bi bi-tag';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'provider', 'codice', 'categoria_id', 'macrotipologia_id', 'nome',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider'  => ['index' => 'provider'],
            'ind_codice'    => ['index' => 'codice'],
            'ind_categoria' => ['index' => 'categoria_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('provider')->text(),
            Field::key('codice')->text(),
            Field::key('categoria_id')->text(),
            Field::key('macrotipologia_id')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
        ];
    }
}
