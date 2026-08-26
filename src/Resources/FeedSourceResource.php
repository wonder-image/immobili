<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\{ApiSchema, FormField, NavigationSchema, PermissionSchema, TableColumn, TableLayoutSchema};
use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Feed\ProviderRegistry;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\System\FeedSource;
use Wonder\Plugin\Immobili\Sync\SyncApiUser;

use Wonder\Elements\Components\{ SectionTitle, Card, Container, HelpText };
use Wonder\Elements\Form\Form;

/**
 * Gestione dei feed collegati al sito (uno o più per gestionale).
 *
 * CRUD standard + rotta backend custom "Sincronizza ora" (`/{id}/sync/`).
 */
final class FeedSourceResource extends Resource
{
    public static string $model = FeedSource::class;

    public static string $orderColumn    = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'immobili-feed';
    }

    public static function icon(): string
    {
        return 'bi bi-rss';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'feed',
            'plural_label' => 'feed',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'name'                => 'Nome',
            'provider'            => 'Gestionale',
            'active'              => 'Attivo',
            'code'                => 'Codice / ID Agenzia',
            'username'            => 'ID Sito (Gestim)',
            'save_images'         => 'Salva immagini in locale',
            'default_visible'     => 'Visibili di default',
            'default_evidence'    => 'In evidenza di default',
            'default_sold'        => 'Venduti di default',
            'last_sync_status'    => 'Ultima sincronizzazione',
            'position'            => 'Ordine',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('name')->text()->required(),
            FormField::key('provider')->select(ProviderRegistry::options())->required(),
            FormField::key('active')->bool()->value('true'),

            FormField::key('code')->text()->visibleWhen('provider', ['getrix', 'gestim']),
            FormField::key('username')->text()->visibleWhen('provider', ['gestim']),

            FormField::key('save_images')->bool()->value('false'),
            FormField::key('default_visible')->bool()->value('true'),
            FormField::key('default_evidence')->bool()->value('false'),
            FormField::key('default_sold')->bool()->value('false'),

            FormField::key('position')->position(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Generale')->columnSpan(12),
                    static::getInput('name')->columnSpan(8),
                    static::getInput('active')->columnSpan(4),
                ])->columns(12)->columnSpan(12),

                (new Card)->components([
                    SectionTitle::make('Feed')->columnSpan(12),
                    static::getInput('provider')->columnSpan(4),
                    static::getInput('code')->columnSpan(4),
                    static::getInput('username')->columnSpan(4),
                    HelpText::make(static::syncHelp())->columnSpan(12),
                ])->columns(12)->columnSpan(12),

            ])->columns(12)->columnSpan(9),
            (new Card)->components([
                SectionTitle::make('Impostazioni')->columnSpan(12),
                static::getInput('save_images')->columnSpan(12),
                static::getInput('default_visible')->columnSpan(12),
                static::getInput('default_evidence')->columnSpan(12),
                static::getInput('default_sold')->columnSpan(12),
            ])->columns(12)->columnSpan(3),
        ])->columns(12);
    }


    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit')->size('medium'),
            TableColumn::key('provider')->badge(),
            TableColumn::key('last_sync_status')->text(),
            TableColumn::key('active')->activeBadge(true)->size('little'),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Feed immobiliari')
            ->buttonAdd('Aggiungi feed')
            ->results()
            ->filters()
            ->searchFields(['name', 'provider', 'code']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'view', 'edit', 'update', 'delete'], ['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title('Feed')
            ->order(20)
            ->authority(['admin', 'immobili_manager']);
    }

    /**
     * Nota secondaria del pannello feed: la sincronizzazione si autentica con
     * il token dell'utente API dedicato `@immobili` (nessuna variabile
     * d'ambiente). Non mostra i comandi cron — rimanda alla documentazione.
     *
     * Provisiona l'utente/token al primo render (idempotente) così l'admin può
     * copiarlo subito per configurare i cron.
     */
    private static function syncHelp(): string
    {
        $docs = Immobili::DOCS_URL.'/riferimento/api-e-sync.md';

        $token = '';
        try {
            $token = SyncApiUser::token();
        } catch (\Throwable $e) {
            $token = '';
        }

        $tokenBlock = $token !== ''
            ? '<br>Token API (Bearer): <code style="word-break:break-all">'.htmlspecialchars($token, ENT_QUOTES).'</code>'
            : '';

        return
            'La sincronizzazione usa un <strong>token API dedicato</strong>.'
            .$tokenBlock
            .'<br><a href="'.htmlspecialchars($docs, ENT_QUOTES).'" target="_blank" rel="noopener">'
            .'Guida alla configurazione dei cron</a>';
    }

    /**
     * Rotta backend custom: sincronizza il feed indicato.
     */
    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::get('/'.$slug.'/{id}/sync/', Immobili::httpPath('backend/feed/sync.php'))
            ->name('resource.'.$slug.'.sync')
            ->permit(['admin', 'immobili_manager']);
    }
}
