<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Quartiere. Tassonomia CANONICA condivisa: la chiave naturale è la coppia
 * (`comune_id`, `nome`) — i quartieri non hanno un codice ISTAT. FK intere a
 * `Comune`/`Regione`/`Provincia`; `getrix_id`/`gestim_id` = codici nativi.
 */
final class Quartiere extends Model
{
    public static string $table = 'immobili_quartieri';
    public static string $icon  = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            Column::key('nome')->varchar()->length(191),
            Column::key('comune_id')->int()->foreign('immobili_comuni')->foreignOnDelete('CASCADE'),
            Column::key('regione_id')->int()->null(),
            Column::key('provincia_id')->int()->null(),
            Column::key('getrix_id')->varchar()->length(64)->null(),
            Column::key('gestim_id')->varchar()->length(64)->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_nome'      => ['index' => 'nome'],
            'ind_comune'    => ['index' => 'comune_id'],
            'ind_getrix_id' => ['index' => 'getrix_id'],
            'ind_gestim_id' => ['index' => 'gestim_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('comune_id')->number()->decimals(0),
            Field::key('regione_id')->number()->decimals(0),
            Field::key('provincia_id')->number()->decimals(0),
            Field::key('getrix_id')->text(),
            Field::key('gestim_id')->text(),
        ];
    }
}
