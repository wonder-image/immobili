<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Tipologia specifica dell'immobile (Appartamento, Villa, Negozio, …).
 *
 * Tassonomia CANONICA condivisa da tutti i gestionali: `chiave` = nostro
 * identificatore stabile, `categoria_id`/`macrotipologia_id` = FK intere alle
 * righe canoniche, `getrix_id`/`gestim_id` = codici nativi dei gestionali.
 */
final class Tipologia extends Model
{
    public static string $table = 'immobili_tipologie';
    public static string $icon  = 'bi bi-tag';

    public static function tableSchema(): array
    {
        return [
            Column::key('chiave')->varchar()->length(96),
            Column::key('nome')->varchar()->length(191),
            Column::key('categoria_id')->int()->foreign('immobili_categorie')->foreignOnDelete('SET NULL'),
            Column::key('macrotipologia_id')->int()->foreign('immobili_macrotipologie')->foreignOnDelete('SET NULL'),
            Column::key('getrix_id')->varchar()->length(64)->null(),
            Column::key('gestim_id')->varchar()->length(64)->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_chiave'    => ['index' => 'chiave'],
            'ind_categoria' => ['index' => 'categoria_id'],
            'ind_macro'     => ['index' => 'macrotipologia_id'],
            'ind_getrix_id' => ['index' => 'getrix_id'],
            'ind_gestim_id' => ['index' => 'gestim_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('chiave')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('categoria_id')->number()->decimals(0),
            Field::key('macrotipologia_id')->number()->decimals(0),
            Field::key('getrix_id')->text(),
            Field::key('gestim_id')->text(),
        ];
    }
}
