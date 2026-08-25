<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\Resource;
use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputCheckBoolean;
use Wonder\App\ResourceSchema\Inputs\InputNumber;
use Wonder\App\ResourceSchema\Inputs\InputPrice;
use Wonder\App\ResourceSchema\Inputs\InputRepeater;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\App\Support\Repeater;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;
use Wonder\Http\Route;
use Wonder\Plugin\Immobili\Immobili;
use Wonder\Plugin\Immobili\Models\Comune;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Models\Quartiere;
use Wonder\Plugin\Immobili\Models\QuartiereZona;
use Wonder\Plugin\Immobili\Models\Tipologia;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;
use Wonder\Plugin\Immobili\Support\ImmobileForm;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Gestione degli immobili.
 *
 * Due origini convivono nella stessa tabella:
 * - **da feed** (`provider` = getrix/gestim/…): i dati arrivano dai gestionali;
 *   dal backend si gestiscono solo i flag manuali (visibile/evidenza/venduto).
 * - **manuali** (`provider` = 'manual', `feed_source_id` = 0): creati e modificati
 *   interamente dal sito, con immagini caricate a mano (webp/resize automatici).
 *   La sincronizzazione dei feed non li tocca.
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
        $labels = [];

        foreach ([
            'nome', 'categoria_id', 'macrotipologia_id', 'tipologia_id',
            'tipologia_nome', 'comune_id', 'comune_nome', 'quartiere_id',
            'quartiere_zona_id', 'strada', 'indirizzo', 'civico', 'cap', 'note',
            'contratto_id', 'prezzo', 'cauzione', 'durata_contratto_id',
            'superficie', 'spese_mensili', 'spese_riscaldamento', 'reddito',
            'tipo_costruzione_id', 'stato_costruzione_id', 'stato_immobile_id',
            'n_camere', 'n_altre_camere', 'n_locali', 'n_bagni', 'cucina_id',
            'arredamento_id', 'box_auto_id', 'n_posti_auto', 'n_balconi',
            'n_terrazzi', 'cantina_id', 'mansarda_id', 'taverna_id',
            'porta_blindata', 'allarme', 'cancello_elettrico', 'videocitofono',
            'fibra_ottica', 'camino', 'n_ascensori', 'infissi_esterni_id',
            'impianto_tv_id', 'giardino_privato_id', 'giardino_condominiale',
            'idromassaggio', 'piscina', 'tennis', 'anno_costruzione', 'piano',
            'n_livelli', 'piani_edificio', 'esposizione_interna',
            'esposizione_esterna', 'riscaldamento_id', 'tipo_riscaldamento_id',
            'acqua_calda_id', 'youtube', 'virtual_tour', 'images', 'floor_plans',
            'legge_classe_energetica_id',
            'classe_energetica', 'ipe', 'stato', 'creation', 'visible',
            'evidence', 'sold',
        ] as $key) {
            $labels[$key] = ImmobileForm::text('fields.'.$key);
        }

        return $labels;
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('nome')->text()->required(),
            FormField::key('categoria_id')->select(ImmobileForm::categories())->required(),
            FormField::key('macrotipologia_id')->select(ImmobileForm::macroTypes())->required(),
            FormField::key('tipologia_id')->select(ImmobileForm::types())->required(),

            FormField::key('comune_id')->selectSearch(ImmobileForm::municipalities())->required(),
            FormField::key('quartiere_id')->select(ImmobileForm::districts()),
            FormField::key('quartiere_zona_id')->select(ImmobileForm::districtZones()),
            FormField::key('strada')->text()->required(),
            FormField::key('indirizzo')->text()->required(),
            FormField::key('civico')->text(),
            FormField::key('cap')->text()->required(),
            FormField::key('note')->textarea(),

            FormField::key('contratto_id')->select(ImmobileForm::options('contract')),
            self::priceField('prezzo'),
            self::priceField('cauzione')->hiddenWhen('contratto_id', ['V']),
            FormField::key('durata_contratto_id')
                ->select(ImmobileForm::options('contract_duration'))
                ->hiddenWhen('contratto_id', ['V']),
            self::numberField('superficie', 0, 'mq'),
            self::priceField('spese_mensili', '€/Mese'),
            self::priceField('spese_riscaldamento', '€/Anno')
                ->hiddenWhen('contratto_id', ['A']),
            self::booleanField('reddito'),

            FormField::key('tipo_costruzione_id')->select(ImmobileForm::options('construction_type')),
            FormField::key('stato_costruzione_id')->select(ImmobileForm::options('construction_status')),
            FormField::key('stato_immobile_id')->select(ImmobileForm::options('occupancy')),

            self::numberField('n_camere')->attribute('data-calc-locali="true"'),
            self::numberField('n_altre_camere')->attribute('data-calc-locali="true"'),
            self::numberField('n_locali')->readonly(),
            self::numberField('n_bagni'),
            FormField::key('cucina_id')->select(ImmobileForm::options('kitchen')),
            FormField::key('arredamento_id')->select(ImmobileForm::options('furnishing')),
            FormField::key('box_auto_id')->select(ImmobileForm::options('garage')),
            self::numberField('n_posti_auto'),

            self::booleanField('n_balconi'),
            self::booleanField('n_terrazzi'),
            self::presenceField('cantina_id'),
            self::presenceField('mansarda_id'),
            self::presenceField('taverna_id'),
            self::booleanField('porta_blindata'),
            self::booleanField('allarme'),
            self::booleanField('cancello_elettrico'),
            self::booleanField('videocitofono'),
            self::booleanField('fibra_ottica'),
            self::booleanField('camino'),
            self::booleanField('n_ascensori'),
            FormField::key('infissi_esterni_id')->select(ImmobileForm::options('window_frames')),
            FormField::key('impianto_tv_id')->select(ImmobileForm::options('tv_system')),

            self::presenceField('giardino_privato_id'),
            self::booleanField('giardino_condominiale'),
            self::booleanField('idromassaggio'),
            self::booleanField('piscina'),
            self::booleanField('tennis'),

            self::numberField('anno_costruzione'),
            self::numberField('piano'),
            self::numberField('n_livelli')->value(1),
            self::numberField('piani_edificio'),
            self::booleanField('esposizione_interna'),
            self::booleanField('esposizione_esterna'),
            FormField::key('riscaldamento_id')->select(ImmobileForm::options('heating')),
            FormField::key('tipo_riscaldamento_id')->select(ImmobileForm::options('heating_fuel')),
            FormField::key('acqua_calda_id')->select(ImmobileForm::options('hot_water')),

            self::mediaRepeater('youtube', 'youtube_url', 'add_youtube'),
            self::mediaRepeater('virtual_tour', 'virtual_tour_url', 'add_virtual_tour'),

            self::imageRepeater('images', 'image', 'add_image', true),
            self::imageRepeater('floor_plans', 'floor_plan', 'add_floor_plan'),

            FormField::key('legge_classe_energetica_id')->select(ImmobileForm::options('energy_law')),
            FormField::key('classe_energetica')->select(ImmobileForm::energyClasses()),
            self::numberField('ipe', 2, 'kWh/mq anno'),

            FormField::key('stato')->select(ImmobileForm::options('status', false))->value('active'),
            FormField::key('sold')->bool()->value('false'),
            FormField::key('evidence')->bool()->value('false'),
            FormField::key('visible')->bool()->value('true'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form())->components([
            (new Container())->components([
                self::card([
                    static::getInput('nome')->columnSpan(12),
                ]),
                self::card([
                    self::section('property_type'),
                    static::getInput('categoria_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('macrotipologia_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('tipologia_id')->columnSpan(['default' => 12, 'md' => 4]),
                ]),
                self::card([
                    self::section('location'),
                    static::getInput('comune_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('quartiere_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('quartiere_zona_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('strada')->columnSpan(['default' => 12, 'md' => 2]),
                    static::getInput('indirizzo')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('civico')->columnSpan(['default' => 6, 'md' => 2]),
                    static::getInput('cap')->columnSpan(['default' => 6, 'md' => 2]),
                ]),
                self::card([
                    static::getInput('note')->columnSpan(12),
                ]),
                self::card([
                    self::section('contract_costs'),
                    static::getInput('contratto_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('prezzo')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('cauzione')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('durata_contratto_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('superficie')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('spese_mensili')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('spese_riscaldamento')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('reddito')->columnSpan(['default' => 12, 'md' => 4]),
                ]),
                self::card([
                    self::section('property_status'),
                    static::getInput('tipo_costruzione_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('stato_costruzione_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('stato_immobile_id')->columnSpan(['default' => 12, 'md' => 4]),
                ]),
                self::card([
                    self::section('composition'),
                    static::getInput('n_camere')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('n_altre_camere')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('n_locali')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('n_bagni')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('cucina_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('arredamento_id')->columnSpan(['default' => 12, 'md' => 3]),
                    static::getInput('box_auto_id')->columnSpan(['default' => 8, 'md' => 3]),
                    static::getInput('n_posti_auto')->columnSpan(['default' => 4, 'md' => 2]),
                ]),
                self::card([
                    self::section('accessories'),
                    static::getInput('n_balconi')->columnSpan(6),
                    static::getInput('n_terrazzi')->columnSpan(6),
                    static::getInput('cantina_id')->columnSpan(6),
                    static::getInput('mansarda_id')->columnSpan(6),
                    static::getInput('taverna_id')->columnSpan(6),
                    static::getInput('porta_blindata')->columnSpan(6),
                    static::getInput('allarme')->columnSpan(6),
                    static::getInput('cancello_elettrico')->columnSpan(6),
                    static::getInput('videocitofono')->columnSpan(6),
                    static::getInput('fibra_ottica')->columnSpan(6),
                    static::getInput('camino')->columnSpan(6),
                    static::getInput('n_ascensori')->columnSpan(6),
                    static::getInput('infissi_esterni_id')->columnSpan(6),
                    static::getInput('impianto_tv_id')->columnSpan(6),
                ], ['default' => 12, 'lg' => 7]),
                self::card([
                    self::section('outdoor'),
                    static::getInput('giardino_privato_id')->columnSpan(12),
                    static::getInput('giardino_condominiale')->columnSpan(12),
                    static::getInput('idromassaggio')->columnSpan(12),
                    static::getInput('piscina')->columnSpan(12),
                    static::getInput('tennis')->columnSpan(12),
                ], ['default' => 12, 'lg' => 5]),
                self::card([
                    self::section('features'),
                    static::getInput('anno_costruzione')->columnSpan(['default' => 12, 'md' => 3]),
                    static::getInput('piano')->columnSpan(['default' => 12, 'md' => 3]),
                    static::getInput('n_livelli')->columnSpan(['default' => 12, 'md' => 3]),
                    static::getInput('piani_edificio')->columnSpan(['default' => 12, 'md' => 3]),
                    static::getInput('esposizione_interna')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('esposizione_esterna')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('riscaldamento_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('tipo_riscaldamento_id')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('acqua_calda_id')->columnSpan(['default' => 12, 'md' => 4]),
                ]),
                self::card([
                    self::section('media'),
                    static::getInput('youtube')->columnSpan(12),
                    static::getInput('virtual_tour')->columnSpan(12),
                ]),
                self::card([
                    static::getInput('images')->columnSpan(12),
                ]),
                self::card([
                    static::getInput('floor_plans')->columnSpan(12),
                ]),
            ])->columns(12)->columnSpan(['default' => 12, 'lg' => 9]),
            (new Container())->components([
                self::card([
                    self::section('energy'),
                    static::getInput('legge_classe_energetica_id')->columnSpan(12),
                    static::getInput('classe_energetica')->columnSpan(12),
                    static::getInput('ipe')->columnSpan(12),
                ]),
                self::card([
                    self::section('details'),
                    static::getInput('stato')->columnSpan(12),
                    static::getInput('sold')->columnSpan(12),
                    static::getInput('evidence')->columnSpan(12),
                    static::getInput('visible')->columnSpan(12),
                    (new Submit('upload'))
                        ->label(ImmobileForm::text('buttons.save'))
                        ->buttonClass('btn btn-dark w-100')
                        ->columnSpan(12),
                ]),
            ])->columns(12)->columnSpan(['default' => 12, 'lg' => 3]),
        ])->columns(12)->gap(3);
    }

    /** @param array<int, object|string> $components */
    private static function card(array $components, int|array $span = 12): Card
    {
        return (new Card())
            ->components($components)
            ->columns(12)
            ->columnSpan($span);
    }

    private static function section(string $key): SectionTitle
    {
        return SectionTitle::make(ImmobileForm::text('sections.'.$key))
            ->level(5)
            ->columnSpan(12);
    }

    private static function numberField(string $key, int $decimals = 0, ?string $symbol = null): InputNumber
    {
        $field = FormField::key($key)
            ->number()
            ->decimal($decimals)
            ->decimalSeparator(',')
            ->groupSeparator('.');

        if ($symbol !== null && $symbol !== '') {
            $field->symbol($symbol)->symbolPlacement('s');
        }

        return $field;
    }

    private static function priceField(string $key, ?string $symbol = null): InputPrice
    {
        $field = FormField::key($key)
            ->price()
            ->decimal(0)
            ->decimalSeparator(',')
            ->groupSeparator('.');

        if ($symbol !== null && $symbol !== '') {
            $field->symbol($symbol)->symbolPlacement('s');
        }

        return $field;
    }

    private static function booleanField(string $key): InputCheckBoolean
    {
        return FormField::key($key)->checkBoolean(
            ['', 'true', 'false'],
            ImmobileForm::text('options.boolean.yes'),
            ImmobileForm::text('options.boolean.no')
        );
    }

    private static function presenceField(string $key): InputCheckBoolean
    {
        return FormField::key($key)->checkBoolean(
            ['', '1', '2'],
            ImmobileForm::text('options.boolean.present'),
            ImmobileForm::text('options.boolean.absent')
        );
    }

    private static function mediaRepeater(string $key, string $fieldLabel, string $addLabel): InputRepeater
    {
        return FormField::key($key)
            ->repeater([
                RepeaterColumn::key('url')
                    ->url()
                    ->label(ImmobileForm::text('fields.'.$fieldLabel))
                    ->columnSpan(9),
            ])
            ->nested()
            ->repeaterSortable()
            ->repeaterAddLabel(ImmobileForm::text('buttons.'.$addLabel));
    }

    private static function imageRepeater(
        string $key,
        string $fieldLabel,
        string $addLabel,
        bool $related = false
    ): InputRepeater {
        $field = FormField::key($key)
            ->repeater([
                RepeaterColumn::key('id')->hidden(),
                RepeaterColumn::key('preview_url')->hidden(),
                RepeaterColumn::key('upload')
                    ->fileDragDrop('image')
                    ->maxSize(3)
                    ->extensions(['png', 'jpg', 'jpeg'])
                    ->label(ImmobileForm::text('fields.'.$fieldLabel))
                    ->columnSpan(12),
            ])
            ->nested()
            ->repeaterSortable()
            ->repeaterAddLabel(ImmobileForm::text('buttons.'.$addLabel));

        if ($related) {
            $field->relation(
                RepeaterRelation::make('immobili_immagini', 'immobile_id')
                    ->positionKey('position')
                    ->softDelete(false)
                    ->model(ImmobileImmagine::class)
            );
        }

        return $field;
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->label('')->size('little'),
            TableColumn::key('image')->image()->formatter(static fn (array $row): string => (new ImmobilePresenter())->coverImage($row))->label('')->size('little')->link('view'),
            TableColumn::key('nome')->formatter(static fn (array $row): string => ImmobilePresenter::nome($row))->link('view'),
            TableColumn::key('comune_nome')->text()->size('medium'),
            TableColumn::key('prezzo')->formatter(static fn (array $row): string => ImmobilePresenter::price($row))->size('medium')->sortable(),
            TableColumn::key('superficie')->formatter(static fn (array $row): string => ImmobilePresenter::formatSurface($row['superficie'] ?? 0))->size('medium')->sortable(),
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
            ->titles([
                'create' => ImmobileForm::text('titles.create', 'Aggiungi immobile'),
                'edit' => ImmobileForm::text('titles.edit', 'Modifica immobile'),
            ])
            ->view('form', static::customFormViewPath())
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
     */
    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        Route::post('/sync/', Immobili::httpPath('backend/feed/sync.php'))
            ->name('sync')
            ->permit(['admin', 'immobili_manager']);

    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        // `stato` è il valore canonico del form. I vecchi flag visible/sold
        // restano il fallback per record legacy che non hanno ancora stato.
        $values['stato'] = self::statusFromValues($values);

        if (!isset($values['n_livelli']) || trim((string) $values['n_livelli']) === '') {
            $values['n_livelli'] = 1;
        }

        foreach (['youtube', 'virtual_tour'] as $mediaField) {
            if (array_key_exists($mediaField, $values)) {
                $values[$mediaField] = self::mediaRepeaterRows(
                    $values[$mediaField],
                    $mediaField === 'youtube'
                );
            }
        }

        foreach (['images', 'floor_plans'] as $imageField) {
            if (is_array($values[$imageField] ?? null)) {
                $values[$imageField] = self::imageFormRows($values[$imageField]);
            }
        }

        return $values;
    }

    /** @param array<int|string, mixed> $rows */
    private static function imageFormRows(array $rows): array
    {
        $presenter = new ImmobilePresenter();

        return array_values(array_map(
            static function (mixed $row) use ($presenter): array {
                $row = is_array($row) ? $row : [];
                $uploadedFile = ImmobileImmagine::firstUploadedFile($row['upload'] ?? '');
                $row['upload'] = $uploadedFile !== ''
                    ? json_encode([$uploadedFile], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                    : '';
                // La preview custom vive dentro FilePond e copre anche il
                // caricamento remoto fallito degli upload già persistiti.
                $row['preview_url'] = $presenter->imagePreview($row);

                return $row;
            },
            $rows
        ));
    }

    /**
     * Immobili da feed: dal form si aggiorna soltanto lo stato editoriale.
     * Immobili manuali: normalizza i campi dipendenti, risolve i nomi delle
     * tassonomie e imposta origine, autore e slug lato server.
     */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $isManual = $action === 'store' || self::isManualRecord($oldValues ?? []);
        $status = self::statusFromValues($values + ($oldValues ?? []));
        [$visible, $sold] = self::statusFlags($status);

        if (!$isManual) {
            return [
                // Valore letto dal record, non dal POST: serve a mantenere il
                // form in modalità feed anche in caso di rerender con errori.
                'provider' => (string) ($oldValues['provider'] ?? ''),
                'stato' => $status,
                'visible' => $visible,
                'sold' => $sold,
            ];
        }

        $values['provider'] = 'manual';
        $values['feed_source_id'] = 0;
        $values['stato'] = $status;
        $values['visible'] = $visible;
        $values['sold'] = $sold;

        if ($action === 'store') {
            $values['creator_type'] = 'user';
            $values['creator_id'] = self::currentUserId();
            $values['feed_deleted'] = 'false';
            // L'evidenza è scelta dall'utente nel form (default 'false' se
            // assente): non va forzata, altrimenti il checkbox è ignorato.
            $values['evidence'] = immobiliIsTrue($values['evidence'] ?? '') ? 'true' : 'false';
        }

        $contract = strtoupper(trim((string) ($values['contratto_id'] ?? '')));
        $values['contratto_id'] = $contract;

        if ($contract === 'V') {
            $values['cauzione'] = '';
            $values['durata_contratto_id'] = '';
        } elseif ($contract === 'A') {
            $values['spese_riscaldamento'] = '';
        }

        if (in_array(trim((string) ($values['riscaldamento_id'] ?? '')), ['', '255'], true)) {
            $values['tipo_riscaldamento_id'] = '';
        }

        $values['n_locali'] = max(0, (int) ($values['n_camere'] ?? 0))
            + max(0, (int) ($values['n_altre_camere'] ?? 0));

        $values = ImmobileForm::normalizeDependentValues($values);

        foreach (['youtube', 'virtual_tour'] as $mediaField) {
            if (array_key_exists($mediaField, $values)) {
                $values[$mediaField] = self::mediaUrls(
                    $values[$mediaField],
                    $mediaField === 'youtube'
                );
            }
        }

        $attributi = immobiliDecodeJsonArray($oldValues['attributi'] ?? []);
        $tipologiaNome = ImmobileForm::taxonomyLabel(Tipologia::class, (string) ($values['tipologia_id'] ?? ''));
        $comuneNome = ImmobileForm::taxonomyLabel(Comune::class, (string) ($values['comune_id'] ?? ''));
        $quartiereNome = ImmobileForm::taxonomyLabel(Quartiere::class, (string) ($values['quartiere_id'] ?? ''));
        $zonaNome = ImmobileForm::taxonomyLabel(QuartiereZona::class, (string) ($values['quartiere_zona_id'] ?? ''));

        $tipologiaNome = $tipologiaNome !== ''
            ? $tipologiaNome
            : trim((string) ($oldValues['tipologia_nome'] ?? $attributi['tipologia'] ?? ''));
        $comuneNome = $comuneNome !== ''
            ? $comuneNome
            : trim((string) ($oldValues['comune_nome'] ?? $attributi['comune'] ?? ''));

        $values['tipologia_nome'] = $tipologiaNome;
        $values['comune_nome'] = $comuneNome;
        $values['quartiere'] = $quartiereNome;
        $values['quartiere_zona'] = $zonaNome;
        $values['zona'] = $zonaNome;
        $attributi['tipologia'] = $tipologiaNome;
        $attributi['comune'] = $comuneNome;
        $values['attributi'] = $attributi;

        if (empty($oldValues['slug'])) {
            $values['slug'] = Slug::fromRow([
                'tipologia_nome' => $tipologiaNome,
                'strada'         => (string) ($values['strada'] ?? ''),
                'indirizzo'      => (string) ($values['indirizzo'] ?? ''),
                'comune_nome'    => $comuneNome,
            ], isset($oldValues['id']) ? (int) $oldValues['id'] : null);
        }

        return $values;
    }

    /**
     * Converte le liste persistite in righe compatibili con il repeater.
     *
     * @return array<int, array{url: string}>
     */
    private static function mediaRepeaterRows(mixed $value, bool $youtube = false): array
    {
        return array_map(
            static fn (string $url): array => ['url' => $url],
            self::mediaUrls($value, $youtube)
        );
    }

    /** @return array<int, string> */
    private static function mediaUrls(mixed $value, bool $youtube = false): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            $decoded = $trimmed !== '' ? json_decode($trimmed, true) : [];
            $value = json_last_error() === JSON_ERROR_NONE
                ? (is_array($decoded) ? $decoded : (is_scalar($decoded) ? [$decoded] : []))
                : [$trimmed];
        }

        if (!is_array($value)) {
            return [];
        }

        $urls = [];

        foreach ($value as $row) {
            $url = is_array($row) ? ($row['url'] ?? '') : $row;

            if (!is_scalar($url)) {
                continue;
            }

            $url = self::mediaUrl((string) $url, $youtube);

            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private static function mediaUrl(string $url, bool $youtube): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if ($youtube) {
            $embedUrl = self::youtubeEmbedUrl($url);

            if ($embedUrl !== '') {
                return $embedUrl;
            }
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    private static function youtubeEmbedUrl(string $value): string
    {
        $videoId = self::youtubeVideoId($value);

        if ($videoId === '') {
            return '';
        }

        $url = 'https://www.youtube.com/embed/'.$videoId;
        $parameters = self::youtubeEmbedParameters($value);

        return $parameters !== []
            ? $url.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)
            : $url;
    }

    /** @return array<string, string|int> */
    private static function youtubeEmbedParameters(string $value): array
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return [];
        }

        parse_str((string) parse_url($value, PHP_URL_QUERY), $query);
        $parameters = [];
        $start = 0;

        foreach (['start', 't', 'time_continue'] as $timeKey) {
            $start = self::youtubeTimeSeconds($query[$timeKey] ?? null);

            if ($start > 0) {
                $parameters['start'] = $start;
                break;
            }
        }

        foreach ($query as $key => $parameter) {
            if (!is_string($key)
                || preg_match('/^[A-Za-z0-9_]+$/', $key) !== 1
                || in_array($key, ['v', 'start', 't', 'time_continue'], true)
                || !is_scalar($parameter)) {
                continue;
            }

            $parameter = trim((string) $parameter);

            if ($parameter !== '') {
                $parameters[$key] = $parameter;
            }
        }

        return $parameters;
    }

    private static function youtubeTimeSeconds(mixed $value): int
    {
        if (!is_scalar($value)) {
            return 0;
        }

        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return max(0, (int) $value);
        }

        if (preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $value, $matches) !== 1) {
            return 0;
        }

        return ((int) ($matches[1] ?? 0) * 3600)
            + ((int) ($matches[2] ?? 0) * 60)
            + (int) ($matches[3] ?? 0);
    }

    private static function youtubeVideoId(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{6,}$/', $value) === 1) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
        $path = trim((string) parse_url($value, PHP_URL_PATH), '/');
        $videoId = '';

        if ($host === 'youtu.be') {
            $videoId = explode('/', $path)[0] ?? '';
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            $segments = array_values(array_filter(explode('/', $path)));

            if (($segments[0] ?? '') === 'watch') {
                parse_str((string) parse_url($value, PHP_URL_QUERY), $query);
                $videoId = (string) ($query['v'] ?? '');
            } elseif (in_array(($segments[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
                $videoId = (string) ($segments[1] ?? '');
            }
        }

        return preg_match('/^[A-Za-z0-9_-]{6,}$/', $videoId) === 1 ? $videoId : '';
    }

    private static function statusFromValues(array $values): string
    {
        $status = strtolower(trim((string) ($values['stato'] ?? '')));

        if (in_array($status, ['active', 'suspended', 'purchased', 'rented'], true)) {
            return $status;
        }

        if (immobiliIsTrue($values['sold'] ?? '')) {
            return strtoupper(trim((string) ($values['contratto_id'] ?? ''))) === 'A'
                ? 'rented'
                : 'purchased';
        }

        if (array_key_exists('visible', $values) && !immobiliIsTrue($values['visible'])) {
            return 'suspended';
        }

        return 'active';
    }

    /** @return array{0: string, 1: string} */
    private static function statusFlags(string $status): array
    {
        return match ($status) {
            'suspended' => ['false', 'false'],
            'purchased', 'rented' => ['true', 'true'],
            default => ['true', 'false'],
        };
    }

    private static function currentUserId(): int
    {
        $user = LegacyGlobals::get('USER');

        return is_object($user) ? (int) ($user->id ?? 0) : 0;
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

    private static function customFormViewPath(): ?string
    {
        $path = Immobili::viewPath('pages/backend/immobili/form.php');

        return is_file($path) ? $path : null;
    }

    /**
     * `floor_plans` è una proiezione virtuale della relazione fisica `images`:
     * entrambe le sezioni vivono in `immobili_immagini`, distinte dai campi
     * `tipo` e `planimetria`.
     */
    public static function stripRelationInputValues(array $values): array
    {
        $values = parent::stripRelationInputValues($values);
        unset($values['floor_plans']);

        return $values;
    }

    public static function appendRepeaterRelationsToItem(array $item): array
    {
        $item = parent::appendRepeaterRelationsToItem($item);

        if (is_array($item['images'] ?? null)) {
            $groups = self::splitImageRows($item['images']);
            $item['images'] = $groups['images'];
            $item['floor_plans'] = $groups['floor_plans'];
        }

        return $item;
    }

    public static function hydrateRepeaterFormValues(
        array $values,
        int|string|null $parentId = null,
        ?array $post = null,
        ?array $files = null
    ): array {
        $post ??= [];
        $files ??= [];
        $imagesRequested = Repeater::hasRowsInRequest('images', $post, $files);
        $floorPlansRequested = Repeater::hasRowsInRequest('floor_plans', $post, $files);
        $values = parent::hydrateRepeaterFormValues($values, $parentId, $post, $files);

        if ($parentId === null || $parentId === '' || (int) $parentId <= 0) {
            return $values;
        }

        if ($imagesRequested && $floorPlansRequested) {
            return $values;
        }

        if (
            !$imagesRequested
            && !$floorPlansRequested
            && is_array($values['images'] ?? null)
            && is_array($values['floor_plans'] ?? null)
        ) {
            return $values;
        }

        $allRows = !$imagesRequested && is_array($values['images'] ?? null)
            ? $values['images']
            : self::loadImageRows($parentId);
        $groups = self::splitImageRows($allRows);

        if (!$imagesRequested) {
            $values['images'] = $groups['images'];
        }

        if (!$floorPlansRequested) {
            $values['floor_plans'] = $groups['floor_plans'];
        }

        return $values;
    }

    /**
     * Le immagini dei record importati appartengono al feed: un salvataggio
     * editoriale dal backend non deve riordinarle, sostituirle o cancellarle.
     */
    public static function syncRepeaterRelations(
        int|string $parentId,
        array $post,
        array $files = [],
        string $action = 'store',
        string $context = 'backend'
    ): array {
        $parentValues = static::query()->Select(
            static::modelTable(),
            ['id' => $parentId],
            1,
            null,
            null,
            ['provider']
        )->row ?? null;

        if (!is_array($parentValues) || !self::isManualRecord($parentValues)) {
            return [];
        }

        $relation = self::imageRelation();

        if (!$relation instanceof RepeaterRelation) {
            return [];
        }

        $imagesRequested = Repeater::hasRowsInRequest('images', $post, $files);
        $floorPlansRequested = Repeater::hasRowsInRequest('floor_plans', $post, $files);
        $existingRows = self::loadImageRows($parentId);
        $existingGroups = self::splitImageRows($existingRows);
        $allowedIds = [];
        $seenIds = [];

        foreach ($existingRows as $existingRow) {
            $existingId = trim((string) ($existingRow['id'] ?? ''));

            if ($existingId !== '') {
                $allowedIds[$existingId] = true;
            }
        }

        $rowsFor = static function (string $inputName, bool $requested) use (
            $post,
            $files,
            $existingGroups,
            $allowedIds,
            &$seenIds
        ): array {
            $rows = $requested
                ? Repeater::rowsFromRequest($inputName, $post, $files)
                : array_map(
                    static fn (array $row): array => ['id' => $row['id'] ?? ''],
                    $existingGroups[$inputName] ?? []
                );
            [$rows, $seenIds] = self::validatedImageRows($rows, $allowedIds, $seenIds);

            return $rows;
        };

        $taggedRows = static function (array $rows, string $inputName): array {
            return array_map(
                static function (array $row) use ($inputName): array {
                    $row['_immobili_media_input'] = $inputName;

                    return $row;
                },
                $rows
            );
        };

        $rows = [
            ...$taggedRows($rowsFor('images', $imagesRequested), 'images'),
            ...$taggedRows($rowsFor('floor_plans', $floorPlansRequested), 'floor_plans'),
        ];

        $summary = Repeater::syncRelatedRows(
            $relation,
            $parentId,
            $rows,
            static function (array $payload, array $row, ?array $existingRow) use ($action, $context): array {
                $inputName = (string) ($row['_immobili_media_input'] ?? 'images');
                unset($payload['_immobili_media_input'], $row['_immobili_media_input']);

                return static::prepareRepeaterRelationRow(
                    $inputName,
                    $payload,
                    $row,
                    $existingRow,
                    $action,
                    $context
                );
            }
        );

        return ['images' => $summary];
    }

    /**
     * Accetta righe esistenti solo se appartengono all'immobile caricato e
     * impedisce che lo stesso ID compaia in entrambe le sezioni media.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, bool> $allowedIds
     * @param array<string, bool> $seenIds
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, bool>}
     */
    private static function validatedImageRows(array $rows, array $allowedIds, array $seenIds): array
    {
        $validated = [];

        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));

            if ($id !== '') {
                if (!isset($allowedIds[$id]) || isset($seenIds[$id])) {
                    continue;
                }

                $row['id'] = $id;
                $seenIds[$id] = true;
            }

            $validated[] = $row;
        }

        return [$validated, $seenIds];
    }

    private static function imageRelation(): ?RepeaterRelation
    {
        $relation = static::repeaterRelations()['images']['relation'] ?? null;

        return $relation instanceof RepeaterRelation ? $relation : null;
    }

    private static function loadImageRows(int|string $parentId): array
    {
        $relation = self::imageRelation();

        return $relation instanceof RepeaterRelation
            ? Repeater::loadRelatedRows($relation, $parentId)
            : [];
    }

    /**
     * @param array<int|string, mixed> $rows
     * @return array{images: array<int, array<string, mixed>>, floor_plans: array<int, array<string, mixed>>}
     */
    private static function splitImageRows(array $rows): array
    {
        $groups = [
            'images' => [],
            'floor_plans' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $isFloorPlan = immobiliIsTrue($row['planimetria'] ?? '')
                || strtoupper(trim((string) ($row['tipo'] ?? ''))) === 'P';
            $groups[$isFloorPlan ? 'floor_plans' : 'images'][] = $row;
        }

        return $groups;
    }

    /** @param array<string, mixed> $values */
    private static function isManualRecord(array $values): bool
    {
        return in_array(
            strtolower(trim((string) ($values['provider'] ?? ''))),
            ['', 'manual'],
            true
        );
    }

    public static function prepareRepeaterRelationRow(
        string $inputName,
        array $payload,
        array $row,
        ?array $existingRow = null,
        string $action = 'store',
        string $context = 'backend'
    ): array {
        if (in_array($inputName, ['images', 'floor_plans'], true)) {
            unset($payload['preview_url']);

            $isFloorPlan = $inputName === 'floor_plans';
            $payload['tipo'] = $isFloorPlan ? 'P' : 'F';
            $payload['planimetria'] = $isFloorPlan ? 'true' : 'false';

            $names = (array) ($row['upload']['name'] ?? []);
            $hasNewUpload = array_filter(
                $names,
                static fn (mixed $name): bool => trim((string) $name) !== ''
            ) !== [];

            if ($hasNewUpload || $existingRow === null) {
                $payload['external_id'] = '';
                $payload['titolo'] = '';
                $payload['resized'] = 'true';
                $payload['source_url'] = '';
                $payload['file'] = '';
            }
        }

        return $payload;
    }
}
