<?php

namespace Wonder\Plugin\Immobili\Resources;

use Wonder\App\LegacyGlobals;
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
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Models\ResidenzaImmagine;
use Wonder\Plugin\Immobili\Services\ResidenzaPresenter;
use Wonder\Plugin\Immobili\Support\ResidenzaForm;

/**
 * Gestione backend delle residenze (cantieri). Record sempre manuali. Gli
 * immobili collegati sono editati qui via multiselect e memorizzati sulla FK
 * `immobili.residenza_id` (nessun pivot), sincronizzata negli hook after*.
 */
final class ResidenzaResource extends Resource
{
    public static string $model = Residenza::class;

    public static string $orderColumn    = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'residenze';
    }

    public static function icon(): string
    {
        return 'bi bi-buildings';
    }

    public static function textSchema(): array
    {
        return [
            'label'        => 'residenza',
            'plural_label' => 'residenze',
        ];
    }

    public static function labelSchema(): array
    {
        $labels = [];

        foreach ([
            'nome', 'sito_url', 'inizio_anno', 'inizio_mese', 'fine_anno', 'fine_mese',
            'descrizione_breve', 'descrizione_lunga', 'indirizzo', 'civico', 'cap',
            'comune_id', 'latitudine', 'longitudine', 'zoom', 'logo', 'images',
            'immobili_collegati', 'features', 'classe_energetica', 'unita_abitative',
            'capitolato', 'stato', 'sold', 'evidence', 'visible', 'position', 'image',
        ] as $key) {
            $labels[$key] = ResidenzaForm::text('fields.'.$key);
        }

        return $labels;
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('nome')->text()->required(),
            FormField::key('sito_url')->url(),

            self::yearField('inizio_anno'),
            self::monthField('inizio_mese'),
            self::yearField('fine_anno'),
            self::monthField('fine_mese'),

            FormField::key('descrizione_breve')->textarea(),
            FormField::key('descrizione_lunga')->textarea(),

            FormField::key('indirizzo')->text(),
            FormField::key('civico')->text(),
            FormField::key('cap')->text(),
            FormField::key('comune_id')->selectSearch(ResidenzaForm::municipalities()),
            FormField::key('latitudine')->text(),
            FormField::key('longitudine')->text(),
            FormField::key('zoom')->text(),

            FormField::key('logo')->fileDragDrop('image')->maxSize(3)->extensions(['png', 'jpg', 'jpeg', 'svg', 'webp']),
            self::imageRepeater(),
            FormField::key('immobili_collegati')->selectSearch(ResidenzaForm::immobili(), true),
            FormField::key('features')->selectSearch(ResidenzaForm::features(), true),

            FormField::key('classe_energetica')->select(ResidenzaForm::energyClasses()),
            self::numberField('unita_abitative'),
            FormField::key('capitolato')->fileDragDrop('file')->maxSize(20)->extensions(['pdf']),

            FormField::key('stato')->select(self::statoOptions()),
            FormField::key('sold')->bool()->value('false'),
            FormField::key('evidence')->bool()->value('false'),
            FormField::key('visible')->bool()->value('true'),
            self::numberField('position'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form())->components([
            (new Container())->components([
                self::card([
                    static::getInput('nome')->columnSpan(['default' => 12, 'md' => 8]),
                    static::getInput('sito_url')->columnSpan(['default' => 12, 'md' => 4]),
                    static::getInput('descrizione_breve')->columnSpan(12),
                    static::getInput('descrizione_lunga')->columnSpan(12),
                ]),
                self::card([
                    self::section('timeline'),
                    static::getInput('inizio_anno')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('inizio_mese')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('fine_anno')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('fine_mese')->columnSpan(['default' => 6, 'md' => 3]),
                ]),
                self::card([
                    self::section('location'),
                    static::getInput('indirizzo')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('civico')->columnSpan(['default' => 6, 'md' => 2]),
                    static::getInput('cap')->columnSpan(['default' => 6, 'md' => 4]),
                    static::getInput('comune_id')->columnSpan(['default' => 12, 'md' => 6]),
                    static::getInput('latitudine')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('longitudine')->columnSpan(['default' => 6, 'md' => 3]),
                    static::getInput('zoom')->columnSpan(['default' => 12, 'md' => 6]),
                ]),
                self::card([
                    self::section('media'),
                    static::getInput('logo')->columnSpan(12),
                    static::getInput('images')->columnSpan(12),
                ]),
                self::card([
                    self::section('linked'),
                    static::getInput('immobili_collegati')->columnSpan(12),
                ]),
                self::card([
                    self::section('features'),
                    static::getInput('features')->columnSpan(12),
                ]),
                self::card([
                    self::section('capitolato'),
                    static::getInput('capitolato')->columnSpan(12),
                ]),
            ])->columns(12)->columnSpan(['default' => 12, 'lg' => 9]),
            (new Container())->components([
                self::card([
                    self::section('energy'),
                    static::getInput('classe_energetica')->columnSpan(12),
                    static::getInput('unita_abitative')->columnSpan(12),
                ]),
                self::card([
                    self::section('publish'),
                    static::getInput('stato')->columnSpan(12),
                    static::getInput('sold')->columnSpan(12),
                    static::getInput('evidence')->columnSpan(12),
                    static::getInput('visible')->columnSpan(12),
                    static::getInput('position')->columnSpan(12),
                    (new Submit('upload'))
                        ->label(ResidenzaForm::text('buttons.save'))
                        ->buttonClass('btn btn-dark w-100')
                        ->columnSpan(12),
                ]),
            ])->columns(12)->columnSpan(['default' => 12, 'lg' => 3]),
        ])->columns(12)->gap(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->label('')->size('little'),
            TableColumn::key('image')->image()->formatter(static fn (array $row): string => self::coverCell($row))->label('')->size('little')->link('view'),
            TableColumn::key('nome')->text()->link('view'),
            TableColumn::key('comune_nome')->text()->size('medium'),
            TableColumn::key('inizio_anno')->formatter(static fn (array $row): string => self::timelineCell($row))->label(ResidenzaForm::text('fields.timeline', 'Timeline'))->size('medium'),
            TableColumn::key('sold')->booleanBadge('sold')
                ->badgeOff('Disponibile', 'bi bi-tag', 'primary')
                ->badgeOn('Venduto', 'bi bi-check2-circle', 'dark')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge(true)->size('little'),
            TableColumn::key('actions')->button()->actions(['view', 'edit', 'visible', 'evidence', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title(ResidenzaForm::text('titles.list', 'Residenze'))
            ->buttonAdd(ResidenzaForm::text('titles.create', 'Aggiungi residenza'))
            ->results()
            ->filters()
            ->searchFields(['nome', 'comune_nome', 'indirizzo'])
            ->filterRadio('Stato', 'sold', ['false' => 'Disponibili', 'true' => 'Vendute']);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['view', 'list', 'create', 'store', 'edit', 'update', 'delete'])
            ->titles([
                'create' => ResidenzaForm::text('titles.create', 'Aggiungi residenza'),
                'edit'   => ResidenzaForm::text('titles.edit', 'Modifica residenza'),
            ]);
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
            ->title(ResidenzaForm::text('titles.list', 'Residenze'))
            ->order(20)
            ->authority(['admin', 'immobili_manager']);
    }

    // --- Form helpers -----------------------------------------------------

    /** @param array<int, object|string> $components */
    private static function card(array $components, int|array $span = 12): Card
    {
        return (new Card())->components($components)->columns(12)->columnSpan($span);
    }

    private static function section(string $key): SectionTitle
    {
        return SectionTitle::make(ResidenzaForm::text('sections.'.$key))->level(5)->columnSpan(12);
    }

    private static function numberField(string $key): FormField
    {
        return FormField::key($key)->number()->decimal(0)->decimalSeparator(',')->groupSeparator('');
    }

    private static function yearField(string $key): FormField
    {
        return FormField::key($key)->number()->decimal(0)->groupSeparator('');
    }

    private static function monthField(string $key): FormField
    {
        return FormField::key($key)->number()->decimal(0)->groupSeparator('');
    }

    /** @return array<string, string> */
    private static function statoOptions(): array
    {
        return [
            ''            => ResidenzaForm::text('options.stato.auto', 'Automatico'),
            'in_arrivo'   => ResidenzaForm::text('options.stato.in_arrivo', 'In arrivo'),
            'in_corso'    => ResidenzaForm::text('options.stato.in_corso', 'In corso'),
            'completato'  => ResidenzaForm::text('options.stato.completato', 'Completato'),
        ];
    }

    private static function imageRepeater(): FormField
    {
        return FormField::key('images')
            ->repeater([
                RepeaterColumn::key('id')->hidden(),
                RepeaterColumn::key('preview_url')->hidden(),
                RepeaterColumn::key('upload')
                    ->fileDragDrop('image')
                    ->maxSize(3)
                    ->extensions(['png', 'jpg', 'jpeg'])
                    ->label(ResidenzaForm::text('fields.image'))
                    ->columnSpan(12),
            ])
            ->nested()
            ->repeaterSortable()
            ->repeaterAddLabel(ResidenzaForm::text('buttons.add_image'))
            ->relation(
                RepeaterRelation::make('immobili_residenze_immagini', 'residenza_id')
                    ->positionKey('position')
                    ->softDelete(false)
                    ->model(ResidenzaImmagine::class)
            );
    }

    // --- Table cell formatters -------------------------------------------

    /** @param array<string, mixed> $row */
    private static function coverCell(array $row): string
    {
        $cover = (new ResidenzaPresenter())->cover($row);

        return $cover === '' ? '' : '<img src="'.htmlspecialchars($cover, ENT_QUOTES).'" alt="" class="w-100 h-100 object-fit-cover">';
    }

    /** @param array<string, mixed> $row */
    private static function timelineCell(array $row): string
    {
        $start = ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0));
        $end = ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0));

        if ($start === '' && $end === '') {
            return '';
        }

        return htmlspecialchars(trim($start.' → '.$end, ' →'), ENT_QUOTES);
    }

    // --- Pure request/form transforms (unit-tested) ----------------------

    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    /**
     * Normalizza le features al catalogo noto: solo id validi, unici, in ordine.
     *
     * @return array<int, string>
     */
    public static function normalizeFeatures(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = $raw !== '' ? json_decode($raw, true) : [];
            $raw = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $known = ResidenzaForm::FEATURE_KEYS;
        $result = [];

        foreach ($raw as $value) {
            $id = is_scalar($value) ? trim((string) $value) : '';

            if ($id !== '' && isset($known[$id]) && !in_array($id, $result, true)) {
                $result[] = $id;
            }
        }

        return $result;
    }

    /**
     * Stato canonico derivato (delega al presenter) usato al salvataggio quando
     * l'utente non forza un override.
     */
    public static function deriveStato(array $values): string
    {
        return ResidenzaPresenter::stato($values);
    }

    /**
     * Calcola gli immobili da agganciare/sganciare confrontando la selezione
     * con lo stato corrente.
     *
     * @param array<int, int|string> $selectedIds
     * @param array<int, int|string> $currentIds
     * @return array{attach: array<int,int>, detach: array<int,int>}
     */
    public static function linkedImmobiliDiff(array $selectedIds, array $currentIds): array
    {
        $selected = array_values(array_unique(array_filter(array_map('intval', $selectedIds), static fn (int $id): bool => $id > 0)));
        $current = array_values(array_unique(array_filter(array_map('intval', $currentIds), static fn (int $id): bool => $id > 0)));

        // `attach` è l'intera selezione target (riapplicarla a un id già
        // collegato è idempotente); `detach` sono gli id collegati usciti
        // dalla selezione.
        return [
            'attach' => $selected,
            'detach' => array_values(array_diff($current, $selected)),
        ];
    }

    // --- Lifecycle --------------------------------------------------------

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $values['sito_url'] = self::sanitizeUrl((string) ($values['sito_url'] ?? ''));
        $values['features'] = self::normalizeFeatures($values['features'] ?? []);

        foreach (['inizio_anno', 'inizio_mese', 'fine_anno', 'fine_mese', 'unita_abitative', 'position'] as $intKey) {
            $raw = trim((string) ($values[$intKey] ?? ''));
            $values[$intKey] = $raw === '' ? '' : (string) (int) $raw;
        }

        // Comune: denormalizza il nome dalla tassonomia; rimuovi la FK vuota.
        $comuneId = (string) ($values['comune_id'] ?? '');
        $values['comune_nome'] = $comuneId !== '' && (int) $comuneId > 0
            ? ResidenzaForm::comuneNome($comuneId)
            : '';

        if ((int) $comuneId <= 0) {
            unset($values['comune_id']);
        }

        // Flag di pubblicazione normalizzati.
        foreach (['sold', 'evidence', 'visible'] as $flag) {
            $values[$flag] = self::isTrue($values[$flag] ?? '') ? 'true' : 'false';
        }

        // Stato: override valido o derivato dalla timeline.
        $override = strtolower(trim((string) ($values['stato'] ?? '')));
        $values['stato'] = in_array($override, ['in_arrivo', 'in_corso', 'completato'], true)
            ? $override
            : self::deriveStato($values);

        // Slug stabile: generato solo se non esiste già sul record.
        if (empty($oldValues['slug'])) {
            $excludeId = isset($oldValues['id']) ? (int) $oldValues['id'] : null;
            $values['slug'] = ResidenzaForm::uniqueSlug((string) ($values['nome'] ?? ''), $excludeId);
        }

        return $values;
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        // Le features persistite (array/JSON) tornano al form come array di id.
        if (array_key_exists('features', $values)) {
            $values['features'] = self::normalizeFeatures($values['features']);
        }

        // Righe gallery: prepara upload JSON + anteprima per FilePond.
        if (is_array($values['images'] ?? null)) {
            $presenter = new ResidenzaPresenter();
            $values['images'] = array_values(array_map(
                static function (mixed $row) use ($presenter): array {
                    $row = is_array($row) ? $row : [];
                    $file = ResidenzaImmagine::firstUploadedFile($row['upload'] ?? '');
                    $row['upload'] = $file !== ''
                        ? json_encode([$file], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                        : '';
                    $row['preview_url'] = $presenter->imagePreview($row);

                    return $row;
                },
                $values['images']
            ));
        }

        return $values;
    }

    public static function prepareRepeaterRelationRow(
        string $inputName,
        array $payload,
        array $row,
        ?array $existingRow = null,
        string $action = 'store',
        string $context = 'backend'
    ): array {
        if ($inputName === 'images') {
            unset($payload['preview_url']);
        }

        return $payload;
    }

    public static function hydrateRepeaterFormValues(
        array $values,
        int|string|null $parentId = null,
        ?array $post = null,
        ?array $files = null
    ): array {
        $values = parent::hydrateRepeaterFormValues($values, $parentId, $post, $files);

        // In edit pre-seleziona gli immobili già collegati (nessun POST attivo).
        $editing = $parentId !== null && $parentId !== '' && (int) $parentId > 0;
        $posted = is_array($post) && array_key_exists('immobili_collegati', $post);

        if ($editing && !$posted) {
            $values['immobili_collegati'] = self::linkedImmobiliIds((int) $parentId);
        }

        return $values;
    }

    public static function afterStore(object $result, array $values = []): void
    {
        $residenzaId = (int) ($result->insert_id ?? 0);

        if ($residenzaId > 0) {
            self::syncLinkedImmobili($residenzaId, (array) ($_POST['immobili_collegati'] ?? []));
        }
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        self::syncLinkedImmobili((int) $id, (array) ($_POST['immobili_collegati'] ?? []));
    }

    /**
     * Applica la selezione del multiselect alla FK immobili.residenza_id.
     *
     * @param array<int, mixed> $selected
     */
    private static function syncLinkedImmobili(int $residenzaId, array $selected): void
    {
        if ($residenzaId <= 0) {
            return;
        }

        $diff = self::linkedImmobiliDiff($selected, self::linkedImmobiliIds($residenzaId));

        foreach ($diff['attach'] as $immobileId) {
            Immobile::update(['residenza_id' => $residenzaId], $immobileId);
        }

        foreach ($diff['detach'] as $immobileId) {
            Immobile::update(['residenza_id' => ''], $immobileId);
        }
    }

    /** @return array<int, string> id immobili attualmente collegati */
    private static function linkedImmobiliIds(int $residenzaId): array
    {
        $rows = Immobile::find(['residenza_id' => $residenzaId]);

        if (is_array($rows) && isset($rows['id'])) {
            $rows = [$rows];
        }

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $row): string => is_array($row) ? (string) ($row['id'] ?? '') : '',
            $rows
        ), static fn (string $id): bool => $id !== ''));
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'si', 'sì', 'yes'], true);
    }
}
