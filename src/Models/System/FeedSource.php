<?php

namespace Wonder\Plugin\Immobili\Models\System;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Feed immobiliare collegato a un sito (uno o più per progetto).
 *
 * Ogni riga rappresenta una connessione a un gestionale: `provider` indica quale
 * adapter usare (getrix|gestim|…), gli altri campi sono le credenziali/opzioni.
 * Le credenziali sono salvate in chiaro perché servono ad autenticarsi al feed.
 */
final class FeedSource extends Model
{
    public static string $table  = 'immobili_feed';
    public static string $folder = 'immobili/feed';
    public static string $icon   = 'bi bi-rss';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow();
    }

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'name',
                'provider',
                'active',
                'code',
                'username',
                'save_images',
                'default_visible',
                'default_evidence',
                'default_sold',
                'last_sync_at',
                'last_sync_status',
                'position',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider' => ['index' => 'provider'],
            'ind_active'   => ['index' => 'active'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('provider')->text()->required(),
            Field::key('active')->text(),

            // Credenziali / connessione (variano per provider):
            //   Getrix → code = Getrix ID
            //   Gestim → code = society_id, username = site_id
            Field::key('code')->text(),
            Field::key('username')->text(),

            // Opzioni di import.
            Field::key('save_images')->text(),
            Field::key('default_visible')->text(),
            Field::key('default_evidence')->text(),
            Field::key('default_sold')->text(),

            // Stato ultima sincronizzazione.
            Field::key('last_sync_at')->date(),
            Field::key('last_sync_status')->text()->sanitize(false),

            Field::key('position')->number()->decimals(0),
        ];
    }
}
