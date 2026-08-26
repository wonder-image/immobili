<?php

namespace Wonder\Plugin\Immobili\Models\Taxonomy;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Comune. Tassonomia CANONICA condivisa: la chiave naturale è il
 * `cod_catastale` (ISTAT, es. F205 = Milano), stabile tra i gestionali.
 * `regione_id`/`provincia_id` = FK intere alle righe canoniche;
 * `getrix_id`/`gestim_id` = codici nativi. Include coordinate e CAP per la mappa.
 */
final class Comune extends Model
{
    public static string $table = 'immobili_comuni';
    public static string $icon  = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            Column::key('cod_catastale')->varchar()->length(8),
            Column::key('nome')->varchar()->length(191),
            Column::key('regione_id')->int()->foreign('immobili_regioni')->foreignOnDelete('SET NULL'),
            Column::key('provincia_id')->int()->foreign('immobili_province')->foreignOnDelete('SET NULL'),
            Column::key('cap')->varchar()->length(16)->null(),
            Column::key('capoluogo')->varchar()->length(8)->null(),
            Column::key('latitudine')->varchar()->length(32)->null(),
            Column::key('longitudine')->varchar()->length(32)->null(),
            Column::key('getrix_id')->varchar()->length(64)->null(),
            Column::key('gestim_id')->varchar()->length(64)->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_catastale' => ['index' => 'cod_catastale'],
            'ind_nome'      => ['index' => 'nome'],
            'ind_provincia' => ['index' => 'provincia_id'],
            'ind_getrix_id' => ['index' => 'getrix_id'],
            'ind_gestim_id' => ['index' => 'gestim_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('cod_catastale')->text()->upper(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('regione_id')->number()->decimals(0),
            Field::key('provincia_id')->number()->decimals(0),
            Field::key('cap')->text(),
            Field::key('capoluogo')->text(),
            Field::key('latitudine')->text(),
            Field::key('longitudine')->text(),
            Field::key('getrix_id')->text(),
            Field::key('gestim_id')->text(),
        ];
    }
}
