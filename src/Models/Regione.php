<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Regione (gerarchia geografica: Regione → Provincia → Comune → Quartiere → Zona).
 *
 * Tassonomia CANONICA condivisa: `chiave` = slug canonico del nome (es.
 * `lombardia`), `getrix_id`/`gestim_id` = codici nativi dei gestionali.
 */
final class Regione extends Model
{
    public static string $table = 'immobili_regioni';
    public static string $icon  = 'bi bi-geo';

    public static function tableSchema(): array
    {
        return [
            Column::key('chiave')->varchar()->length(96),
            Column::key('nome')->varchar()->length(191),
            Column::key('getrix_id')->varchar()->length(64)->null(),
            Column::key('gestim_id')->varchar()->length(64)->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_chiave'    => ['index' => 'chiave'],
            'ind_getrix_id' => ['index' => 'getrix_id'],
            'ind_gestim_id' => ['index' => 'gestim_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('chiave')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('getrix_id')->text(),
            Field::key('gestim_id')->text(),
        ];
    }
}
