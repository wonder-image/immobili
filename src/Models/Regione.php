<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Regione (gerarchia geografica: Regione → Provincia → Comune → Quartiere → Zona).
 */
final class Regione extends Model
{
    public static string $table = 'immobili_regioni';
    public static string $icon  = 'bi bi-geo';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema(['provider', 'codice', 'nome']),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider' => ['index' => 'provider'],
            'ind_codice'   => ['index' => 'codice'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('provider')->text(),
            Field::key('codice')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
        ];
    }
}
