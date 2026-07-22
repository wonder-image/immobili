<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Plugin\Immobili\Models\SyncLog;

/**
 * Storico delle sincronizzazioni (sola lettura).
 *
 * Mostra i file/sorgenti importati, i conteggi e gli esiti di ogni run di
 * sincronizzazione e del secondo piano immagini.
 */
final class SyncLogResource extends Resource
{
    public static string $model = SyncLog::class;

    public static string $orderColumn    = 'creation';
    public static string $orderDirection = 'DESC';

    public static function path(): string
    {
        return 'immobili-log';
    }

    public static function icon(): string
    {
        return 'bi bi-clock-history';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'sincronizzazione',
            'plural_label' => 'sincronizzazioni',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'creation'       => 'Data',
            'provider'       => 'Gestionale',
            'kind'           => 'Tipo',
            'source'         => 'Sorgente / file',
            'immobili_count' => 'Immobili',
            'images_count'   => 'Immagini',
            'status'         => 'Esito',
            'message'        => 'Dettaglio',
        ];
    }

    public static function formSchema(): array
    {
        return [];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('creation')->datetime()->size('medium'),
            TableColumn::key('provider')->badge()->size('little'),
            TableColumn::key('kind')->badge()->size('little'),
            TableColumn::key('source')->text(),
            TableColumn::key('immobili_count')->text()->size('little'),
            TableColumn::key('images_count')->text()->size('little'),
            TableColumn::key('status')->badge()->size('little'),
            TableColumn::key('actions')->button()->actions(['delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Storico sincronizzazioni')
            ->hideButtonAdd()
            ->results()
            ->filters()
            ->searchFields(['provider', 'kind', 'source', 'message']);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->only(['list', 'delete']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'delete'], ['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title('Storico sync')
            ->order(30)
            ->authority(['admin', 'immobili_manager']);
    }
}
