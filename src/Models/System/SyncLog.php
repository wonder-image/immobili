<?php

namespace Wonder\Plugin\Immobili\Models\System;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Storico delle sincronizzazioni.
 *
 * Registra ogni esecuzione di sync (e del secondo piano immagini): il feed, il
 * provider, il **file/sorgente** ricevuto o importato (zip Getrix, callback
 * Gestim, endpoint), i conteggi e l'esito. Serve ad avere uno storico dei file
 * inviati/importati. Non partecipa all'export/import tra ambienti.
 */
final class SyncLog extends Model
{
    public static string $table = 'immobili_sync_log';
    public static string $icon  = 'bi bi-clock-history';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'feed_source_id',
                'provider',
                'kind',
                'source',
                'immobili_count',
                'images_count',
                'status',
                'message',
            ]),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_feed' => ['index' => 'feed_source_id'],
            'ind_kind' => ['index' => 'kind'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('feed_source_id')->number()->decimals(0),
            Field::key('provider')->text(),
            // Tipo di operazione: 'sync' | 'images'.
            Field::key('kind')->text(),
            // File/sorgente ricevuto o importato (URL zip, callback, endpoint).
            Field::key('source')->text()->sanitize(false),
            Field::key('immobili_count')->number()->decimals(0),
            Field::key('images_count')->number()->decimals(0),
            // 'ok' | 'error'.
            Field::key('status')->text(),
            Field::key('message')->text()->sanitize(false),
        ];
    }
}
