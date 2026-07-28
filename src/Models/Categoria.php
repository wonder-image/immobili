<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Categoria immobile (Residenziale, Commerciale, Attività, Terreno, Vacanze, …).
 *
 * Tassonomia CANONICA e provider-agnostica: una sola riga per categoria reale,
 * condivisa da tutti i gestionali. La `chiave` è il nostro identificatore
 * stabile (es. `residenziale`); i codici nativi dei gestionali sono conservati
 * nelle colonne mappa `getrix_id` / `gestim_id` (estendibili con nuovi provider).
 */
final class Categoria extends Model
{
    public static string $table = 'immobili_categorie';
    public static string $icon  = 'bi bi-tags';

    public static function tableSchema(): array
    {
        return [
            Column::key('chiave')->varchar()->length(64),
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
