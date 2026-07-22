<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Macrotipologia (sotto-categoria) immobile. FK a `Categoria`.
 */
final class Macrotipologia extends Model
{
    public static string $table = 'immobili_macrotipologie';
    public static string $icon  = 'bi bi-tags';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema(['provider', 'codice', 'categoria_id', 'nome']),
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
            Field::key('nome')->text()->sanitizeFirst(),
        ];
    }
}
