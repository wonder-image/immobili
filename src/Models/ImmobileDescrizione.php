<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Descrizione di un immobile in una lingua (base del bilingua).
 *
 * I feed dei gestionali forniscono le descrizioni per lingua (IT, EN, …): ognuna
 * è salvata come riga con la propria `lingua`. Il frontend seleziona la lingua
 * corrente (`__l()`) con fallback IT.
 */
final class ImmobileDescrizione extends Model
{
    public static string $table = 'immobili_descrizioni';
    public static string $icon  = 'bi bi-card-text';

    public static function tableSchema(): array
    {
        return [
            // Testo lungo: colonna TEXT esplicita (escluso dalla generazione auto).
            Column::key('testo')->type('TEXT')->null(),
            ...static::sqlColumnsFromDataSchema([
                'immobile_id',
                'lingua',
                'titolo',
                'testo_breve',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_immobile' => ['index' => 'immobile_id'],
            'ind_lingua'   => ['index' => 'lingua'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('immobile_id')->number()->decimals(0),
            Field::key('lingua')->text()->lower(),
            Field::key('titolo')->text()->sanitizeFirst(),
            Field::key('testo')->text()->sanitize(false),
            Field::key('testo_breve')->text()->sanitizeFirst(),
        ];
    }
}
