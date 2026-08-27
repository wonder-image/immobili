<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\System\SyncLog;

/**
 * Storico delle sincronizzazioni (sola lettura).
 *
 * Mostra i file/sorgenti importati, i conteggi e gli esiti di ogni run di
 * sincronizzazione e del secondo piano immagini. Le righe non sono eliminabili:
 * sono un registro storico. Per ogni run è disponibile il download di un report
 * (orari + problematiche + riferimento all'artifact archiviato).
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

            // Esito come badge colorato (Successo/Errore), non testo semplice.
            // Il formatter possiede l'intera cella e riceve la riga grezza.
            TableColumn::key('status')->formatter(static function (array $row): string {
                $ok = ($row['status'] ?? '') === 'ok';

                return '<span class="badge text-bg-'.($ok ? 'success' : 'danger').'">'
                    .($ok ? 'Successo' : 'Errore').'</span>';
            })->size('little'),

            // Nessun delete: le righe sono un registro storico. Solo download.
            TableColumn::key('actions')->button()->actions(['download']),
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
        // Sola lettura: nessuna pagina di delete.
        return PageSchema::for(static::class)->only(['list']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        // Nessun permesso di delete: le righe non si eliminano.
        return PermissionSchema::for(static::class)
            ->backend(['list'], ['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title('Storico sync')
            ->order(30)
            ->authority(['admin', 'immobili_manager']);
    }

    /**
     * Rotta backend custom: download del report di un run di sincronizzazione
     * (orari + problematiche + riferimento all'artifact). Il nome
     * `resource.{slug}.download` è quello che la tabella usa per l'azione
     * "Scarica" per-riga.
     */
    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::get('/'.$slug.'/{id}/download/', Immobili::httpPath('backend/sync-log/download.php'))
            ->name('resource.'.$slug.'.download')
            ->permit(['admin', 'immobili_manager']);
    }
}
