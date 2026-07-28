<?php

namespace Wonder\Plugin\Immobili\Models;

use LogicException;
use Wonder\App\Model;
use Wonder\Backend\Table\Badge\BooleanBadge;
use Wonder\Data\UploadSchema as Field;

/**
 * Immobile importato da un feed o creato manualmente.
 *
 * Modello canonico condiviso da tutti i gestionali: `provider` indica la
 * sorgente (getrix|gestim|…), `feed_source_id` il feed di origine, `external_id`
 * l'id nativo nel gestionale. I campi `*_id` fanno riferimento alle tassonomie
 * (`Categoria`, `Tipologia`, `Comune`, …). Gli attributi specifici dei feed
 * restano conservati fedelmente nel campo
 * JSON `attributi`; i dati modificabili dal form backend hanno colonne proprie.
 *
 * I flag manuali `visible` / `evidence` / `sold` sono gestiti in backend e
 * preservati dal FeedSyncService a ogni sincronizzazione.
 */
final class Immobile extends Model
{
    public static string $table  = 'immobili';
    public static string $folder = 'immobili';
    public static string $icon   = 'bi bi-house-door';

    /**
     * Colonne testuali libere: TEXT evita che il loro contenuto contribuisca
     * al limite InnoDB di 65.535 byte per riga.
     *
     * @var array<int, string>
     */
    private const SQL_TEXT_COLUMNS = [
        'nome',
        'quartiere',
        'quartiere_zona',
        'zona',
        'strada',
        'indirizzo',
        'note',
        'planimetria',
        'virtual_tour',
        'visual_tour',
        'video',
    ];

    private const MEDIA_COLUMNS = [
        'youtube',
        'planimetria',
        'virtual_tour',
        'visual_tour',
        'video',
    ];

    private const SQL_VARCHAR_LENGTHS = [
        'code' => 32,
        'provider' => 64,
        'external_id' => 191,
        'creator_type' => 64,
        'feed_deleted' => 5,
        'evidence' => 5,
        'visible' => 5,
        'sold' => 5,
        'stato' => 16,
        'civico' => 32,
        'cap' => 16,
        'pub_indirizzo' => 5,
        'pub_civico' => 5,
        'pub_mappa' => 5,
        'latitudine' => 32,
        'longitudine' => 32,
        'zoom' => 8,
        'contratto_id' => 8,
        'durata_contratto_id' => 16,
        'situazione_id' => 64,
        'tipo_proprieta_id' => 64,
        'trattativa_riservata' => 5,
        'asta' => 5,
        'pregio' => 5,
        'reddito' => 5,
        'spese_mensili' => 32,
        'spese_id' => 64,
        'legge_classe_energetica_id' => 16,
        'classe_energetica' => 16,
        'ipe' => 32,
        'riscaldamento_id' => 16,
        'tipo_riscaldamento_id' => 16,
        'acqua_calda_id' => 16,
        'tipo_costruzione_id' => 16,
        'stato_costruzione_id' => 16,
        'stato_immobile_id' => 16,
        'anno_costruzione' => 16,
        'piano' => 64,
        'piani_edificio' => 8,
        'n_terrazzi' => 5,
        'n_balconi' => 5,
        'n_ascensori' => 5,
        'esposizione_interna' => 5,
        'esposizione_esterna' => 5,
        'giardino_condominiale' => 5,
        'giardino_privato_id' => 16,
        'idromassaggio' => 5,
        'piscina' => 5,
        'tennis' => 5,
        'cucina_id' => 16,
        'arredamento_id' => 16,
        'box_auto_id' => 16,
        'cantina_id' => 16,
        'mansarda_id' => 16,
        'taverna_id' => 16,
        'porta_blindata' => 5,
        'allarme' => 5,
        'cancello_elettrico' => 5,
        'videocitofono' => 5,
        'fibra_ottica' => 5,
        'camino' => 5,
        'infissi_esterni_id' => 16,
        'impianto_tv_id' => 16,
        'slug' => 191,
        'comune_nome' => 191,
        'tipologia_nome' => 191,
    ];

    /**
     * Default SQL dei flag di pubblicazione: un nuovo immobile è visibile, non
     * in evidenza e non venduto anche senza passare dal form (feed, insert
     * diretti). Il form del backend li ripropone via `FormField->value()`.
     *
     * @var array<string, string>
     */
    private const SQL_DEFAULTS = [
        'visible'  => 'true',
        'evidence' => 'false',
        'sold'     => 'false',
    ];

    /**
     * Colonne con relazione esterna a una tassonomia canonica: diventano INT con
     * FOREIGN KEY verso l'id della tabella. Gli altri `*_id` (contratto, cucina,
     * riscaldamento, …) sono enum di attributo, non FK: restano VARCHAR.
     *
     * @var array<string, string>
     */
    private const FK_COLUMNS = [
        'categoria_id'      => 'immobili_categorie',
        'macrotipologia_id' => 'immobili_macrotipologie',
        'tipologia_id'      => 'immobili_tipologie',
        'comune_id'         => 'immobili_comuni',
        'quartiere_id'      => 'immobili_quartieri',
        'quartiere_zona_id' => 'immobili_quartieri_zone',
    ];

    public static function tableSchema(): array
    {
        $columns = static::sqlColumnsFromDataSchema();

        foreach ($columns as $column) {
            $name = (string) ($column->name ?? '');

            if (isset(self::SQL_DEFAULTS[$name])) {
                $column->default(self::SQL_DEFAULTS[$name]);
            }

            // FK intere verso le tassonomie canoniche: INT(10) per combaciare con
            // l'id (INT(10)) della tabella referenziata. La colonna deriva da un
            // campo ->number() (lunghezza decimale '10,2'): senza reimpostare la
            // length uscirebbe `INT(10,2)`, sintassi non valida. Nullable
            // (immobili manuali/seed possono non averla) + FK con SET NULL.
            if (isset(self::FK_COLUMNS[$name])) {
                $column->type('INT')->length(10)->null()
                    ->foreign(self::FK_COLUMNS[$name])->foreignOnDelete('SET NULL');
                continue;
            }

            if (in_array($name, self::SQL_TEXT_COLUMNS, true)) {
                $column->type('TEXT');
                continue;
            }

            if (strtoupper((string) ($column->schema['type'] ?? 'VARCHAR')) !== 'VARCHAR') {
                continue;
            }

            $length = self::SQL_VARCHAR_LENGTHS[$name] ?? null;

            if ($length === null) {
                throw new LogicException("Lunghezza SQL non definita per immobili.{$name}");
            }

            $column->length($length);
        }

        return $columns;
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_provider'   => ['index' => 'provider'],
            'ind_feed'       => ['index' => 'feed_source_id'],
            'ind_external'   => ['index' => 'external_id'],
            'ind_visible'    => ['index' => 'visible'],
            'ind_sold'       => ['index' => 'sold'],
            'ind_comune'     => ['index' => 'comune_id'],
            'ind_contratto'  => ['index' => 'contratto_id'],
            'ind_tipologia'  => ['index' => 'tipologia_id'],
            'ind_slug'       => ['index' => 'slug'],
            'ind_comune_nome'    => ['index' => 'comune_nome'],
            'ind_tipologia_nome' => ['index' => 'tipologia_nome'],
            'ind_prezzo'     => ['index' => 'prezzo'],
            'ind_superficie' => ['index' => 'superficie'],
        ];
    }

    public static function dataSchema(): array
    {
        return [

            Field::key('code')->text()->uniqueCode('imm_'),

            // Origine / sincronizzazione
            Field::key('provider')->text(),
            Field::key('feed_source_id')->number()->decimals(0),
            Field::key('external_id')->text(),
            Field::key('creator_type')->text(),
            Field::key('creator_id')->number()->decimals(0),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('external_modified_at')->date(),
            Field::key('feed_deleted')->text(),
            Field::key('synced_at')->date(),

            // Stato pubblicazione
            Field::key('evidence')->text(),
            Field::key('visible')->text(),
            Field::key('sold')->text(),
            Field::key('stato')->text(),

            // Classificazione (FK intere alle tassonomie canoniche)
            Field::key('categoria_id')->number()->decimals(0),
            Field::key('macrotipologia_id')->number()->decimals(0),
            Field::key('tipologia_id')->number()->decimals(0),

            // Localizzazione (comune/quartiere/zona = FK intere canoniche)
            Field::key('comune_id')->number()->decimals(0),
            Field::key('quartiere_id')->number()->decimals(0),
            Field::key('quartiere_zona_id')->number()->decimals(0),
            Field::key('quartiere')->text(),
            Field::key('quartiere_zona')->text(),
            Field::key('zona')->text(),
            Field::key('strada')->text(),
            Field::key('indirizzo')->text(),
            Field::key('civico')->text(),
            Field::key('cap')->text(),
            Field::key('note')->text(),
            Field::key('pub_indirizzo')->text(),
            Field::key('pub_civico')->text(),
            Field::key('pub_mappa')->text(),
            Field::key('latitudine')->text(),
            Field::key('longitudine')->text(),
            Field::key('zoom')->text(),

            // Contratto / commerciale
            Field::key('contratto_id')->text(),
            Field::key('durata_contratto_id')->text(),
            Field::key('situazione_id')->text(),
            Field::key('tipo_proprieta_id')->text(),
            Field::key('prezzo')->number()->decimals(0),
            Field::key('cauzione')->number()->decimals(0),
            Field::key('trattativa_riservata')->text(),
            Field::key('asta')->text(),
            Field::key('pregio')->text(),
            Field::key('reddito')->text(),
            Field::key('spese_mensili')->text(),
            Field::key('spese_riscaldamento')->number()->decimals(0),
            Field::key('spese_id')->text(),

            // Energia
            Field::key('legge_classe_energetica_id')->text(),
            Field::key('classe_energetica')->text(),
            Field::key('ipe')->text(),
            Field::key('riscaldamento_id')->text(),
            Field::key('tipo_riscaldamento_id')->text(),
            Field::key('acqua_calda_id')->text(),

            // Caratteristiche fisiche
            Field::key('tipo_costruzione_id')->text(),
            Field::key('stato_costruzione_id')->text(),
            Field::key('stato_immobile_id')->text(),
            Field::key('anno_costruzione')->text(),
            Field::key('piano')->text(),
            Field::key('piani_edificio')->text(),
            Field::key('superficie')->number()->decimals(0),
            Field::key('n_locali')->number()->decimals(0),
            Field::key('n_camere')->number()->decimals(0),
            Field::key('n_altre_camere')->number()->decimals(0),
            Field::key('n_bagni')->number()->decimals(0),
            Field::key('n_terrazzi')->text(),
            Field::key('n_balconi')->text(),
            Field::key('n_posti_auto')->number()->decimals(0),
            Field::key('n_ascensori')->text(),
            Field::key('n_livelli')->number()->decimals(0),
            Field::key('esposizione_interna')->text(),
            Field::key('esposizione_esterna')->text(),
            Field::key('giardino_condominiale')->text(),
            Field::key('giardino_privato_id')->text(),
            Field::key('idromassaggio')->text(),
            Field::key('piscina')->text(),
            Field::key('tennis')->text(),
            Field::key('cucina_id')->text(),
            Field::key('arredamento_id')->text(),
            Field::key('box_auto_id')->text(),
            Field::key('cantina_id')->text(),
            Field::key('mansarda_id')->text(),
            Field::key('taverna_id')->text(),
            Field::key('porta_blindata')->text(),
            Field::key('allarme')->text(),
            Field::key('cancello_elettrico')->text(),
            Field::key('videocitofono')->text(),
            Field::key('fibra_ottica')->text(),
            Field::key('camino')->text(),
            Field::key('infissi_esterni_id')->text(),
            Field::key('impianto_tv_id')->text(),

            // Media (array di URL: colonne JSON, decodificate in array in lettura)
            Field::key('youtube')->json(),
            Field::key('planimetria')->json(),
            Field::key('virtual_tour')->json(),
            Field::key('visual_tour')->json(),
            Field::key('video')->json(),

            // Attributi estesi / polimorfici (dotazioni, impianti, ecc.)
            Field::key('attributi')->json(),

            Field::key('slug')->text()->slug(),

            // Derivati per la ricerca SQL (denormalizzati al sync)
            Field::key('comune_nome')->text(),
            Field::key('tipologia_nome')->text(),
        ];
    }

    public static function decorate(array $row): array
    {
        $row = self::normalizeMediaFields($row);
        $slug = (string) ($row['slug'] ?? '');

        # Url
            $row['url'] = __r('immobile.view', [ 'slug' => $slug ]);
            $row['qrcode'] = immobiliQrCodeUrl((string) ($row['external_id'] ?? ''));

            # PDF
            $row['url_scheda'] = __r('immobile.scheda', [ 'slug' => $slug ]);
            $row['url_cartello'] = __r('immobile.cartello', [ 'slug' => $slug ]);
            $row['url_cartello_vetrina'] = __r('immobile.cartello.vetrina', [ 'slug' => $slug ]);
            $row['url_cartello_vetrina_venduto'] = __r('immobile.cartello.vetrina.venduto', [ 'slug' => $slug ]);

        return $row;
    }

    /**
     * Normalizza in array le colonne media, sia liste JSON sia URL singoli.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeMediaFields(array $row): array
    {
        foreach (self::MEDIA_COLUMNS as $name) {
            if (array_key_exists($name, $row)) {
                $row[$name] = self::mediaList($row[$name]);
            }
        }

        return $row;
    }

    /**
     * `safeFind*()` normalizza le colonne JSON dopo `decorate()`. Il framework
     * interpreta soltanto stringhe JSON e trasformerebbe quindi gli array media
     * gia decorati in `[]`; li preserviamo dopo la normalizzazione standard.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected static function normalizeApiRow(array $row): array
    {
        $media = [];

        foreach (self::MEDIA_COLUMNS as $name) {
            if (array_key_exists($name, $row)) {
                $media[$name] = self::mediaList($row[$name]);
            }
        }

        $row = parent::normalizeApiRow($row);

        foreach ($media as $name => $urls) {
            $row[$name] = $urls;
        }

        return $row;
    }

    /**
     * Restituisce URL media unici e sicuri per l'uso in src HTML.
     * Sono ammessi URL relativi e schemi http/https.
     *
     * @return array<int, string>
     */
    private static function mediaList(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return [];
            }

            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $urls = [];

        foreach ($value as $url) {
            if (!is_scalar($url)) {
                continue;
            }

            $url = trim((string) $url);

            if ($url !== '') {
                $scheme = parse_url($url, PHP_URL_SCHEME);

                if ($scheme !== null && (
                    !is_string($scheme)
                    || !in_array(strtolower($scheme), ['http', 'https'], true)
                )) {
                    continue;
                }

                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Badge di stato per la scheda backend: stato editoriale, venduto, visibile,
     * in evidenza. `visible`/`evidence` usano il BooleanBadge di sistema; `stato`
     * e `sold` restano logica di dominio del modulo (fuori dal framework).
     * Restituisce HTML pronto (contenitore flex), '' se non c'è nulla da mostrare.
     *
     * @param array<string, mixed> $row riga immobile grezza
     */
    public static function statusBadges(array $row): string
    {
        $statoBadge = match (strtolower(trim((string) ($row['stato'] ?? '')))) {
            'active'    => self::domainBadge('Attivo', 'success'),
            'suspended' => self::domainBadge('Sospeso', 'secondary'),
            'purchased' => self::domainBadge('Acquistato', 'dark'),
            'rented'    => self::domainBadge('Affittato', 'dark'),
            default     => '',
        };

        $badges = array_filter([
            $statoBadge,
            immobiliIsTrue($row['sold'] ?? '') ? self::domainBadge('Venduto', 'dark') : '',
            BooleanBadge::preset('visible', immobiliIsTrue($row['visible'] ?? ''))?->badge() ?? '',
            BooleanBadge::preset('evidence', immobiliIsTrue($row['evidence'] ?? ''))?->badge() ?? '',
        ]);

        return $badges === []
            ? ''
            : "<div class='d-flex flex-wrap gap-2'>" . implode(' ', $badges) . '</div>';
    }

    /**
     * Badge di dominio (stato/venduto) con la stessa marcatura del BooleanBadge
     * di sistema: `badge text-bg-{variante}`, etichetta in maiuscolo.
     */
    private static function domainBadge(string $label, string $variant): string
    {
        return "<span class='badge text-bg-{$variant}'>"
            . htmlspecialchars(mb_strtoupper($label), ENT_QUOTES)
            . '</span>';
    }

}
