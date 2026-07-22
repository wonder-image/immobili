<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Categoria immobile (Residenziale, Commerciale, Attività, Terreno, Vacanze, …).
 * Tassonomia importata dal gestionale; `provider` + `codice` la scoping per feed.
 */
final class Categoria extends Model
{
    public static string $table = 'immobili_categorie';
    public static string $icon  = 'bi bi-tags';

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
