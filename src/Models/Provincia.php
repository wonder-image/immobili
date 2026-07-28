<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Provincia. Tassonomia CANONICA condivisa: `sigla` = chiave canonica (MI, RM…),
 * `regione_id` = FK intera alla regione canonica, `getrix_id`/`gestim_id` =
 * codici nativi dei gestionali.
 */
final class Provincia extends Model
{
    public static string $table = 'immobili_province';
    public static string $icon  = 'bi bi-geo';

    public static function tableSchema(): array
    {
        return [
            Column::key('sigla')->varchar()->length(8),
            Column::key('nome')->varchar()->length(191),
            Column::key('regione_id')->int()->foreign('immobili_regioni')->foreignOnDelete('SET NULL'),
            Column::key('getrix_id')->varchar()->length(64)->null(),
            Column::key('gestim_id')->varchar()->length(64)->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_sigla'     => ['index' => 'sigla'],
            'ind_regione'   => ['index' => 'regione_id'],
            'ind_getrix_id' => ['index' => 'getrix_id'],
            'ind_gestim_id' => ['index' => 'gestim_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('sigla')->text()->upper(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('regione_id')->number()->decimals(0),
            Field::key('getrix_id')->text(),
            Field::key('gestim_id')->text(),
        ];
    }
}
