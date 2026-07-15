<?php

namespace Wonder\Plugin\Immobili\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Immobile importato da un feed.
 *
 * Modello canonico condiviso da tutti i gestionali: `provider` indica la
 * sorgente (getrix|gestim|…), `feed_source_id` il feed di origine, `external_id`
 * l'id nativo nel gestionale. I campi `*_id` fanno riferimento alle tassonomie
 * (`Categoria`, `Tipologia`, `Comune`, …). Gli attributi polimorfici/estesi
 * (dotazioni, impianti, ecc.) sono conservati fedelmente nel campo JSON
 * `attributi`, così il modello resta ricco senza centinaia di colonne rigide.
 *
 * I flag manuali `visible` / `evidence` / `sold` sono gestiti in backend e
 * preservati dal FeedSyncService a ogni sincronizzazione.
 */
final class Immobile extends Model
{
    public static string $table  = 'immobili';
    public static string $folder = 'immobili';
    public static string $icon   = 'bi bi-house-door';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                // Origine / sincronizzazione
                'provider', 'feed_source_id', 'external_id', 'nome',
                'external_modified_at', 'feed_deleted', 'synced_at',
                // Stato pubblicazione
                'evidence', 'visible', 'sold',
                // Classificazione
                'categoria_id', 'macrotipologia_id', 'tipologia_id',
                // Localizzazione
                'comune_id', 'quartiere', 'quartiere_zona', 'zona',
                'strada', 'indirizzo', 'civico', 'cap',
                'pub_indirizzo', 'pub_civico', 'pub_mappa',
                'latitudine', 'longitudine', 'zoom',
                // Contratto / commerciale
                'contratto_id', 'durata_contratto_id', 'situazione_id',
                'tipo_proprieta_id',
                'prezzo', 'affitto', 'prezzo_affitto',
                'trattativa_riservata', 'trattativa_riservata_affitto',
                'asta', 'pregio', 'reddito', 'spese_mensili', 'spese_id',
                // Energia
                'legge_classe_energetica_id', 'classe_energetica', 'ipe',
                // Caratteristiche fisiche
                'anno_costruzione', 'piano', 'piani_edificio', 'superficie',
                'n_locali', 'n_camere', 'n_bagni', 'n_terrazzi', 'n_balconi',
                'n_posti_auto', 'giardino_condominiale', 'giardino_privato_id',
                'cucina_id', 'taverna_id',
                // Media
                'youtube_1', 'youtube_2', 'youtube_3', 'youtube_4',
                'planimetria', 'virtual_tour', 'visual_tour', 'video',
                // Attributi estesi / polimorfici + derivati persistiti
                'attributi', 'dir', 'url', 'qrcode',
                'comune_nome', 'tipologia_nome', 'ricerca',
            ]),
        ];
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
            'ind_dir'        => ['index' => 'dir'],
            'ind_comune_nome'    => ['index' => 'comune_nome'],
            'ind_tipologia_nome' => ['index' => 'tipologia_nome'],
            'ind_prezzo'     => ['index' => 'prezzo'],
            'ind_superficie' => ['index' => 'superficie'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            // Origine / sincronizzazione
            Field::key('provider')->text(),
            Field::key('feed_source_id')->number()->decimals(0),
            Field::key('external_id')->text(),
            Field::key('nome')->text()->sanitizeFirst(),
            Field::key('external_modified_at')->date(),
            Field::key('feed_deleted')->text(),
            Field::key('synced_at')->date(),

            // Stato pubblicazione
            Field::key('evidence')->text(),
            Field::key('visible')->text(),
            Field::key('sold')->text(),

            // Classificazione (FK tassonomie)
            Field::key('categoria_id')->text(),
            Field::key('macrotipologia_id')->text(),
            Field::key('tipologia_id')->text(),

            // Localizzazione
            Field::key('comune_id')->text(),
            Field::key('quartiere')->text(),
            Field::key('quartiere_zona')->text(),
            Field::key('zona')->text(),
            Field::key('strada')->text(),
            Field::key('indirizzo')->text(),
            Field::key('civico')->text(),
            Field::key('cap')->text(),
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
            Field::key('affitto')->text(),
            Field::key('prezzo_affitto')->number()->decimals(0),
            Field::key('trattativa_riservata')->text(),
            Field::key('trattativa_riservata_affitto')->text(),
            Field::key('asta')->text(),
            Field::key('pregio')->text(),
            Field::key('reddito')->text(),
            Field::key('spese_mensili')->text(),
            Field::key('spese_id')->text(),

            // Energia
            Field::key('legge_classe_energetica_id')->text(),
            Field::key('classe_energetica')->text(),
            Field::key('ipe')->text(),

            // Caratteristiche fisiche
            Field::key('anno_costruzione')->text(),
            Field::key('piano')->text(),
            Field::key('piani_edificio')->text(),
            Field::key('superficie')->number()->decimals(0),
            Field::key('n_locali')->number()->decimals(0),
            Field::key('n_camere')->number()->decimals(0),
            Field::key('n_bagni')->number()->decimals(0),
            Field::key('n_terrazzi')->number()->decimals(0),
            Field::key('n_balconi')->number()->decimals(0),
            Field::key('n_posti_auto')->number()->decimals(0),
            Field::key('giardino_condominiale')->text(),
            Field::key('giardino_privato_id')->text(),
            Field::key('cucina_id')->text(),
            Field::key('taverna_id')->text(),

            // Media
            Field::key('youtube_1')->text(),
            Field::key('youtube_2')->text(),
            Field::key('youtube_3')->text(),
            Field::key('youtube_4')->text(),
            Field::key('planimetria')->text(),
            Field::key('virtual_tour')->text(),
            Field::key('visual_tour')->text(),
            Field::key('video')->text(),

            // Attributi estesi / polimorfici (dotazioni, impianti, ecc.)
            Field::key('attributi')->json(),

            // Derivati persistiti
            Field::key('dir')->text()->slug(),
            Field::key('url')->text(),
            Field::key('qrcode')->text(),

            // Derivati per la ricerca SQL (denormalizzati al sync)
            Field::key('comune_nome')->text(),
            Field::key('tipologia_nome')->text(),
            Field::key('ricerca')->text(),
        ];
    }
}
