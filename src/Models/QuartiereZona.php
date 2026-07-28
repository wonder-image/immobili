<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Zona di un quartiere (livello geografico più fine). Tassonomia CANONICA
 * condivisa: chiave naturale (`quartiere_id`, `nome`). FK intere a
 * `Quartiere`/`Comune`; `getrix_id`/`gestim_id` = codici nativi dei gestionali.
 */
final class QuartiereZona extends Model
{
    public static string $table = 'immobili_quartieri_zone';
    public static string $icon  = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            Column::key('nome')->varchar()->length(191),
            Column::key('quartiere_id')->int()->foreign('immobili_quartieri')->foreignOnDelete('CASCADE'),
            Column::key('comune_id')->int()->null(),
            Column::key('getrix_id')->varchar()->length(64)->null(),
            Column::key('gestim_id')->varchar()->length(64)->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_nome'      => ['index' => 'nome'],
            'ind_quartiere' => ['index' => 'quartiere_id'],
            'ind_getrix_id' => ['index' => 'getrix_id'],
            'ind_gestim_id' => ['index' => 'gestim_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('quartiere_id')->number()->decimals(0),
            Field::key('comune_id')->number()->decimals(0),
            Field::key('getrix_id')->text(),
            Field::key('gestim_id')->text(),
        ];
    }
}
