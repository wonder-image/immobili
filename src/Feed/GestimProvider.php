<?php

namespace Wonder\Plugin\Immobili\Feed;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Plugin\Gestim\Import;
use Wonder\Plugin\Immobili\Feed\Contracts\FeedProvider;

/**
 * Provider Gestim.
 *
 * Gestim è push-based: il gestionale chiama l'endpoint di sync passando in
 * `callback` l'URL dello ZIP del feed. Riusa l'SDK del framework
 * `Wonder\Plugin\Gestim\Import` (File/Immobili/Tipologie/LookupValue) e mappa sul
 * modello canonico. Porta la logica del legacy bgstar
 * `api/task/gestim/importa.php` + `custom/function/gestim/*`.
 *
 * Gestim non richiede né URL feed né password: tutti i dati (immobili, agenzie,
 * lookup/tipologie) arrivano nello ZIP inviato in push. Mappatura config →
 * colonne generiche del feed:
 *   - code      → society_id (id società Gestim)
 *   - username  → site_id (id sito Gestim)
 */
final class GestimProvider implements FeedProvider
{
    public const KEY = 'gestim';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Gestim';
    }

    public function configSchema(): array
    {
        return [
            FormField::key('code')->text()->label('Società (society_id)')->required(),
            FormField::key('username')->text()->label('Sito (site_id)')->required(),
        ];
    }

    // ---------------------------------------------------------------- Immobili

    public function fetchListings(FeedSourceConfig $feed): iterable
    {
        // Gestim invia lo ZIP tramite il parametro `callback` sulla richiesta.
        $callback = trim((string) ($_REQUEST['callback'] ?? ''));

        if ($callback === '' || $feed->code === '') {
            return;
        }

        $import = new Import();
        $import->File($callback, $feed->code, $feed->username);

        $listings = $import->Immobili();

        if (!is_array($listings)) {
            return;
        }

        if (isset($listings['id'])) {
            $listings = [$listings];
        }

        foreach ($listings as $immobile) {
            if (!is_array($immobile)) {
                continue;
            }

            $listing = $this->normalize($immobile, $import);

            if ($listing !== null) {
                yield $listing;
            }
        }
    }

    /**
     * @param array<string, mixed> $immobile
     */
    private function normalize(array $immobile, Import $import): ?NormalizedListing
    {
        $externalId = (string) ($immobile['id'] ?? '');

        if ($externalId === '') {
            return null;
        }

        $tipologiaId = (string) ($immobile['Tipologia'] ?? '');
        $comune = $this->clean($immobile['Comune'] ?? '');
        $indirizzo = $this->clean($immobile['Indirizzo'] ?? '');
        $motivazione = (string) $import->LookupValue('Motivazione', $immobile['Motivazione'] ?? '');

        $listing = new NormalizedListing($externalId);
        $listing->nome = (string) (!empty($immobile['Codice']) ? $immobile['Codice'] : $externalId);
        $listing->externalModifiedAt = $this->date($immobile['data_aggiornamento'] ?? '');
        $listing->createdAt = $this->date($immobile['prima_pubblicazione'] ?? '');

        $listing->set('nome', $listing->nome);
        $listing->set('tipologia_id', $tipologiaId);
        // Senza un file tipologie separato la macrotipologia non è disponibile.
        $listing->set('macrotipologia_id', '');
        $listing->set('categoria_id', '');

        // Localizzazione (Gestim fornisce nomi, non codici: comune_id resta vuoto
        // e i nomi vanno negli attributi come fallback per il presenter).
        $listing->set('comune_id', '');
        $listing->set('indirizzo', $indirizzo);
        $listing->set('strada', '');
        $listing->set('civico', (string) ($immobile['civico'] ?? ''));
        $listing->set('cap', (string) ($immobile['CAP'] ?? ''));
        $listing->set('latitudine', (string) ($immobile['latitudine'] ?? ''));
        $listing->set('longitudine', (string) ($immobile['longitudine'] ?? ''));
        $listing->set('pub_indirizzo', 'true');
        $listing->set('pub_civico', 'true');
        $listing->set('pub_mappa', 'true');

        // Contratto: Gestim usa "Motivazione" (Vendita/Affitto) → V/A canonici.
        $affitto = stripos($motivazione, 'affitt') !== false || stripos($motivazione, 'locaz') !== false;
        $listing->set('contratto_id', $affitto ? 'A' : 'V');
        $listing->set('prezzo', $this->int($immobile['Prezzo_Richiesto'] ?? ''));
        $listing->set('spese_mensili', $this->spese($immobile['spese_condominiali'] ?? ''));
        $listing->set('trattativa_riservata', ((int) ($immobile['trattativa_riservata'] ?? 0) === 1) ? 'true' : 'false');
        $listing->set('superficie', $this->int($immobile['Totale_mq'] ?? ''));

        // Disposizione
        $listing->set('piano', $this->piano($immobile, $import));
        $listing->set('piani_edificio', (string) ($immobile['piani_totali'] ?? ''));
        $listing->set('n_locali', (string) ($immobile['Locali'] ?? ''));
        $listing->set('n_camere', (string) ($immobile['camere'] ?? ''));
        $listing->set('n_bagni', (string) ($immobile['bagni'] ?? ''));

        // Energia
        $listing->set('classe_energetica', (string) $import->LookupValue('classe_energetica', $immobile['classe_energetica'] ?? ''));

        // Stato
        $stato = (string) ($immobile['stato_immo'] ?? '');
        $listing->evidence = ((int) ($immobile['evidenza'] ?? 0) === 1) ? 'true' : 'false';
        $listing->sold = in_array($stato, ['4', '7', 'venduto'], true) ? 'true' : 'false';

        // Nomi e dotazioni negli attributi (fallback presenter + dettaglio).
        $listing->attribute('comune', $comune);
        $listing->attribute('provincia', $this->clean($immobile['Provincia'] ?? ''));
        $listing->attribute('regione', $this->clean($immobile['Regione'] ?? ''));
        $listing->attribute('tipologia', (string) $import->LookupValue('Tipologia', $immobile['Tipologia'] ?? ''));
        $listing->attribute('motivazione', $motivazione);

        foreach ([
            'Box', 'Terrazzo', 'Cucina', 'Posto_Auto', 'Garage', 'Balconi',
            'Giardino', 'Soffitta',
        ] as $field) {
            $label = (string) $import->LookupValue($field, $immobile[$field] ?? '');
            if ($label !== '') {
                $listing->attribute(strtolower($field), $label);
            }
        }

        $this->collectDescriptions($listing, $immobile);
        $this->collectImages($listing, $immobile);

        return $listing;
    }

    /**
     * @param array<string, mixed> $immobile
     */
    private function collectDescriptions(NormalizedListing $listing, array $immobile): void
    {
        $testo = $immobile['Disposizione_interna']['it'] ?? '';

        if (is_array($testo) || trim((string) $testo) === '') {
            return;
        }

        $listing->addDescription([
            'lingua'      => 'it',
            'titolo'      => '',
            'testo'       => $this->clean($testo),
            'testo_breve' => '',
        ]);

        // Eventuali altre lingue nella disposizione interna.
        foreach (($immobile['Disposizione_interna'] ?? []) as $lingua => $value) {
            if ($lingua === 'it' || is_array($value) || trim((string) $value) === '') {
                continue;
            }

            $listing->addDescription([
                'lingua'      => strtolower((string) $lingua),
                'titolo'      => '',
                'testo'       => $this->clean($value),
                'testo_breve' => '',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $immobile
     */
    private function collectImages(NormalizedListing $listing, array $immobile): void
    {
        $foto = $immobile['foto_lista']['foto'] ?? [];

        if ($foto === [] || !is_array($foto)) {
            return;
        }

        if (!isset($foto[0])) {
            $foto = [$foto];
        }

        foreach ($foto as $immagine) {
            if (!is_array($immagine)) {
                continue;
            }

            $url = (string) ($immagine['nome'] ?? '');
            $planimetria = ((int) ($immagine['planimetria'] ?? 0) === 1) ? 'true' : 'false';

            // Solo la massima risoluzione (immagine piena): il resize è delegato
            // all'ImageProcessor.
            $listing->addImage([
                'external_id' => (string) ($immagine['id'] ?? ''),
                'tipo'        => $planimetria === 'true' ? 'P' : 'F',
                'planimetria' => $planimetria,
                'position'    => (string) ($immagine['ordinamento'] ?? ''),
                'titolo'      => (isset($immagine['titolo']['it']) && !is_array($immagine['titolo']['it'])) ? $this->clean($immagine['titolo']['it']) : '',
                'source_url'  => $url,
            ]);
        }
    }

    // ------------------------------------------------------------- Tassonomie

    public function syncTaxonomies(FeedSourceConfig $feed): void
    {
        // No-op: Gestim non espone un file tipologie separato (nessun URL feed).
        // Le tipologie/lookup arrivano nello ZIP inviato in push e vengono
        // risolte inline durante l'import degli immobili (LookupValue), quindi
        // non c'è una tassonomia da pre-popolare qui.
    }

    // ------------------------------------------------------------------ Utils

    /**
     * @param array<string, mixed> $immobile
     */
    private function piano(array $immobile, Import $import): string
    {
        $label = (string) $import->LookupValue('Piano', $immobile['Piano'] ?? '');

        return $label !== '' ? $label : (string) ($immobile['Piano'] ?? '');
    }

    private function spese(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = str_replace(['€', '+', ' ', ',00'], '', $value);

        return is_numeric($value) ? (string) (int) $value : '';
    }

    /**
     * Normalizza un valore di testo del feed. Usa sanitize() del framework per la
     * gestione charset/unicode, ma ne annulla l'addslashes: l'escaping SQL è già
     * garantito dal layer di persistenza (real_escape_string), quindi lasciare le
     * slash farebbe finire nel DB testo come "Sant\'Angelo".
     */
    private function clean(mixed $value): string
    {
        if (is_array($value)) {
            return '';
        }

        $value = (string) $value;

        return function_exists('sanitize') ? stripslashes((string) sanitize($value)) : trim($value);
    }

    private function int(mixed $value): string
    {
        $value = str_replace(['.', ',', ' ', '€'], '', (string) $value);

        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        return (string) (int) $value;
    }

    private function date(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $time = strtotime($value);

        return $time === false ? '' : date('Y-m-d H:i:s', $time);
    }
}
