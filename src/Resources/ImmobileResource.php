<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Button;
use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\ImmobileDescrizione;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Gestione degli immobili.
 *
 * Due origini convivono nella stessa tabella:
 * - **da feed** (`provider` = getrix/gestim/…): i dati arrivano dai gestionali;
 *   dal backend si gestiscono solo i flag manuali (visibile/evidenza/venduto).
 * - **manuali** (`provider` = 'manual', `feed_source_id` = 0): creati e modificati
 *   interamente dal sito, con immagini caricate a mano (webp/resize automatici) e
 *   descrizioni it/en. La sincronizzazione dei feed non li tocca.
 */
final class ImmobileResource extends Resource
{
    public static string $model = Immobile::class;

    public static string $orderColumn    = 'creation';
    public static string $orderDirection = 'DESC';

    public static function path(): string
    {
        return 'immobili';
    }

    public static function icon(): string
    {
        return 'bi bi-house-door';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'immobile',
            'plural_label' => 'immobili',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'nome'              => 'Riferimento',
            'tipologia_nome'    => 'Tipologia',
            'contratto_id'      => 'Contratto',
            'prezzo'            => 'Prezzo',
            'superficie'        => 'Superficie',
            'n_locali'          => 'Locali',
            'n_camere'          => 'Camere',
            'n_bagni'           => 'Bagni',
            'comune_nome'       => 'Comune',
            'strada'            => 'Indirizzo',
            'civico'            => 'Civico',
            'cap'               => 'CAP',
            'latitudine'        => 'Latitudine',
            'longitudine'       => 'Longitudine',
            'classe_energetica' => 'Classe energetica',
            'descrizione_it'    => 'Descrizione (IT)',
            'descrizione_en'    => 'Descrizione (EN)',
            'immagini'          => 'Immagini',
            'creation'          => 'Inserimento',
            'visible'           => 'Visibile',
            'evidence'          => 'In evidenza',
            'sold'              => 'Stato',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('nome')->text()->required(),
            FormField::key('tipologia_nome')->text(),
            FormField::key('contratto_id')->select(['V' => 'Vendita', 'A' => 'Affitto'])->value('V'),
            FormField::key('prezzo')->price(),
            FormField::key('superficie')->number(),
            FormField::key('n_locali')->number(),
            FormField::key('n_camere')->number(),
            FormField::key('n_bagni')->number(),

            FormField::key('comune_nome')->text(),
            FormField::key('strada')->text(),
            FormField::key('civico')->text(),
            FormField::key('cap')->text(),
            FormField::key('latitudine')->text(),
            FormField::key('longitudine')->text(),
            FormField::key('classe_energetica')->select([
                'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F', 'G' => 'G',
            ]),

            FormField::key('descrizione_it')->textarea(),
            FormField::key('descrizione_en')->textarea(),

            FormField::key('immagini')
                ->repeater([
                    RepeaterColumn::key('upload')->fileDragDrop('image')->label('Immagine')->columnSpan(6),
                    RepeaterColumn::key('titolo')->text()->label('Titolo')->columnSpan(5),
                    RepeaterColumn::key('planimetria')->bool()->label('Planimetria')->columnSpan(1),
                ])
                ->repeaterSortable()
                ->label('Immagini')
                ->relation(
                    RepeaterRelation::make('immobili_immagini', 'immobile_id')
                        ->positionKey('position')
                        ->softDelete(false)
                        ->model(ImmobileImmagine::class)
                ),

            FormField::key('visible')->bool()->value('true'),
            FormField::key('evidence')->bool()->value('false'),
            FormField::key('sold')->bool()->value('false'),
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->label('')->size('little'),
            // Immagine: 2ª colonna (virtuale). Il formatter fornisce l'URL della
            // copertina; il tipo image lo avvolge nell'<img> (lato framework).
            TableColumn::key('image')->image()->formatter(static fn (array $row): string => (new ImmobilePresenter())->coverImage($row))->label('')->size('little'),
            TableColumn::key('nome')->formatter(static fn (array $row): string => ImmobilePresenter::nome($row)),
            TableColumn::key('comune_nome')->text()->size('medium'),
            TableColumn::key('prezzo')->formatter(static fn (array $row): string => ImmobilePresenter::price($row)),
            TableColumn::key('superficie')->formatter(static fn (array $row): string => ImmobilePresenter::formatSurface($row['superficie'] ?? 0))->size('little'),
            TableColumn::key('creation')->date()->sortable(),
            TableColumn::key('sold')->booleanBadge('sold')
                ->badgeOff('In vendita', 'bi bi-tag', 'primary')
                ->badgeOn('Venduto', 'bi bi-check2-circle', 'dark')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge(true)->size('little'),
            TableColumn::key('actions')->button()->actions(['view', 'edit', 'visible', 'evidence', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Immobili')
            ->buttonAdd('Aggiungi immobile')
            ->buttonCustom(
                Button::post(
                    __r('backend.resource.'.static::slug().'.sync'),
                    (string) __t('components.immobili.backend.sync_all.button')
                )
                    ->confirm((string) __t('components.immobili.backend.sync_all.confirm'))
                    ->variant('primary')
                    ->icon('bi bi-arrow-repeat')
            )
            ->results()
            ->filters()
            ->searchFields(['nome', 'comune_nome', 'indirizzo', 'quartiere', 'zona'])
            ->filterRadio('Contratto', 'contratto_id', ['V' => 'Vendita', 'A' => 'Affitto'])
            ->filterRadio('Stato', 'sold', ['false' => 'Disponibili', 'true' => 'Venduti']);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['view', 'list', 'create', 'store', 'edit', 'update', 'delete'])
            ->view('show', static::customShowViewPath());
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'edit', 'update', 'delete'], ['admin', 'immobili_manager']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('immobili', 'Immobili', 'bi-house-door', 500, ['admin', 'immobili_manager'])
            ->title('Immobili')
            ->order(10)
            ->authority(['admin', 'immobili_manager']);
    }

    /**
     * Rotte backend custom dell'immobile. Registrate già dentro il gruppo con
     * prefisso `/immobili` e nome `resource.immobili.`, quindi i path/nomi sono
     * relativi:
     *  - `/backend/immobili/sync/`               → sincronizza tutti i feed;
     *  - `/backend/immobili/{id}/cartello/`      → download del cartello;
     *  - `/backend/immobili/{id}/vetrina/`       → download del cartello vetrina.
     */
    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::post('/sync/', Immobili::httpPath('backend/feed/sync.php'))
            ->name('sync')
            ->permit(['admin', 'immobili_manager']);

        Route::get('/{id}/cartello/', Immobili::httpPath('backend/immobile/cartello.php'))
            ->name('cartello')
            ->permit(['admin', 'immobili_manager'])
            ->where('id', '[0-9]+');

        Route::get('/{id}/vetrina/', Immobili::httpPath('backend/immobile/vetrina.php'))
            ->name('vetrina')
            ->permit(['admin', 'immobili_manager'])
            ->where('id', '[0-9]+');
    }

    /**
     * Pre-compila i campi non-colonna (tipologia/comune dai `attributi`,
     * descrizioni dalle relative righe) quando si modifica un immobile.
     */
    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        $id = (int) ($values['id'] ?? 0);

        if ($id > 0) {
            $attributi = immobiliDecodeJsonArray($values['attributi'] ?? []);
            $values['tipologia_nome'] = (string) ($attributi['tipologia'] ?? '');
            $values['comune_nome'] = (string) ($attributi['comune'] ?? '');
            $values['descrizione_it'] = self::descrizioneTesto($id, 'it');
            $values['descrizione_en'] = self::descrizioneTesto($id, 'en');
        }

        return $values;
    }

    /**
     * Immobili da feed: solo i flag manuali sono scrivibili. Immobili manuali
     * (o nuovi): imposta provider/feed, attributi (tipologia/comune) e slug.
     */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $oldProvider = (string) ($oldValues['provider'] ?? '');
        $isManual = $action === 'store' || $oldProvider === 'manual' || $oldProvider === '';

        if (!$isManual) {
            return array_intersect_key($values, array_flip(['visible', 'evidence', 'sold']));
        }

        $values['provider'] = 'manual';
        $values['feed_source_id'] = 0;

        $attributi = immobiliDecodeJsonArray($oldValues['attributi'] ?? []);
        $attributi['tipologia'] = trim((string) ($values['tipologia_nome'] ?? ''));
        $attributi['comune'] = trim((string) ($values['comune_nome'] ?? ''));
        $values['attributi'] = $attributi;

        if (empty($oldValues['slug'])) {
            $base = Slug::base([
                (string) ($values['tipologia_nome'] ?? ''),
                (string) ($values['strada'] ?? ''),
                (string) ($values['comune_nome'] ?? ''),
            ]);
            $values['slug'] = Slug::unique($base, isset($oldValues['id']) ? (int) $oldValues['id'] : null);
        }

        // tipologia_nome / comune_nome / descrizione_* non sono colonne: il Model
        // le ignora in persistenza, ma restano in $values per afterStore/afterUpdate.
        return $values;
    }

    public static function afterStore(object $result, array $values = []): void
    {
        self::saveDescriptions((int) ($result->insert_id ?? 0), $values);
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        self::saveDescriptions((int) $id, $values);
    }

    /**
     * View custom read-only della pagina `view` backend: la `show.php` del
     * modulo (overridabile dal sito). `null` se assente → il framework ricade
     * sulla show generica. Il template sta nel modulo, quindi il path va
     * risolto con Immobili::viewPath(), non con ROOT_APP del framework.
     */
    private static function customShowViewPath(): ?string
    {
        $path = Immobili::viewPath('pages/backend/immobili/show.php');

        return is_file($path) ? $path : null;
    }

    public static function prepareRepeaterRelationRow(
        string $inputName,
        array $payload,
        array $row,
        ?array $existingRow = null,
        string $action = 'store',
        string $context = 'backend'
    ): array {
        if ($inputName === 'immagini') {
            $payload['tipo'] = immobiliIsTrue($payload['planimetria'] ?? '') ? 'P' : 'F';
            $payload['resized'] = 'true';
            $payload['source_url'] = '';
            $payload['file'] = '';
        }

        return $payload;
    }

    private static function saveDescriptions(int $id, array $values): void
    {
        if ($id <= 0) {
            return;
        }

        foreach (['it' => $values['descrizione_it'] ?? '', 'en' => $values['descrizione_en'] ?? ''] as $lingua => $testo) {
            $testo = trim((string) $testo);
            $existing = ImmobileDescrizione::find(['immobile_id' => $id, 'lingua' => $lingua], 1);
            $breve = mb_substr(strip_tags($testo), 0, 160);

            if (is_array($existing) && isset($existing['id'])) {
                ImmobileDescrizione::update(['testo' => $testo, 'testo_breve' => $breve], (int) $existing['id']);
            } elseif ($testo !== '') {
                ImmobileDescrizione::create([
                    'immobile_id' => $id,
                    'lingua'      => $lingua,
                    'titolo'      => '',
                    'testo'       => $testo,
                    'testo_breve' => $breve,
                ]);
            }
        }
    }

    private static function descrizioneTesto(int $id, string $lingua): string
    {
        $row = ImmobileDescrizione::find(['immobile_id' => $id, 'lingua' => $lingua], 1);

        return is_array($row) ? (string) ($row['testo'] ?? '') : '';
    }
}
