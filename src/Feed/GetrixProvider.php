<?php

namespace Wonder\Plugin\Immobili\Feed;

use RuntimeException;
use SimpleXMLElement;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Plugin\Getrix\Import;
use Wonder\Plugin\Immobili\Feed\Contracts\ArchivesFeedArtifact;
use Wonder\Plugin\Immobili\Feed\Contracts\FeedProvider;
use Wonder\Plugin\Immobili\Models\Taxonomy\Categoria;
use Wonder\Plugin\Immobili\Models\Taxonomy\Comune;
use Wonder\Plugin\Immobili\Models\Taxonomy\Macrotipologia;
use Wonder\Plugin\Immobili\Models\Taxonomy\Provincia;
use Wonder\Plugin\Immobili\Models\Taxonomy\Quartiere;
use Wonder\Plugin\Immobili\Models\Taxonomy\QuartiereZona;
use Wonder\Plugin\Immobili\Models\Taxonomy\Regione;
use Wonder\Plugin\Immobili\Models\Taxonomy\Tipologia;
use Wonder\Plugin\Immobili\Support\Slug;
use Wonder\Plugin\Immobili\Support\Taxonomy;

/**
 * Provider Getrix.
 *
 * Riusa l'SDK del framework `Wonder\Plugin\Getrix\Import` (feed XML zippato da
 * feed.getrix.it) e mappa immobili e tassonomie sul modello canonico. Porta la
 * logica dei legacy `api/task/getrix/{immobili,implementazioni}.php`.
 */
final class GetrixProvider implements FeedProvider, ArchivesFeedArtifact
{
    public const KEY = 'getrix';

    private const LISTINGS_ENDPOINT = 'https://feed.getrix.it/xml/';

    private string $lastArtifactPath = '';

    public function __construct(
        private readonly string $listingsEndpoint = self::LISTINGS_ENDPOINT,
        private readonly ?string $archiveRoot = null,
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Getrix';
    }

    public function configSchema(): array
    {
        return [
            FormField::key('code')
                ->text()
                ->label('Getrix ID')
                ->required(),
        ];
    }

    // ---------------------------------------------------------------- Immobili

    public function fetchListings(FeedSourceConfig $feed): iterable
    {
        $code = trim((string) ($feed->code ?? ''));

        if ($code === '') {
            throw new RuntimeException('Getrix ID mancante nella configurazione del feed.');
        }

        $xml = $this->downloadAndParse($feed, $code);
        $feedItems = 0;
        $normalizedItems = 0;

        foreach ($xml->children() as $node) {
            if ($node->getName() !== 'Immobile') {
                continue;
            }

            $feedItems++;
            $listing = $this->normalize($node);

            if ($listing !== null) {
                $normalizedItems++;
                yield $listing;
            }
        }

        if ($feedItems > 0 && $normalizedItems === 0) {
            throw new RuntimeException(
                'Il file Getrix contiene '.$feedItems.' immobili, ma nessuno ha un ID importabile.'
            );
        }
    }

    public function lastArtifactPath(): string
    {
        return $this->lastArtifactPath;
    }

    private function normalize(mixed $node): ?NormalizedListing
    {
        $tipologiaId = '';
        if (isset($node->Tipologia['IDTipologia'])) {
            $tipologiaId = (string) $node->Tipologia['IDTipologia'];
        }

        $data = json_decode(json_encode($node), true);

        if (!is_array($data)) {
            return null;
        }

        // Nelle specifiche Getrix 3.1 IDImmobile è un attributo del nodo
        // <Immobile>, non un elemento figlio. Il fallback mantiene la
        // compatibilità con eventuali feed legacy che lo espongono come nodo.
        $externalId = trim((string) (
            $data['IDImmobile']
            ?? $data['@attributes']['IDImmobile']
            ?? ''
        ));
        if ($externalId === '') {
            return null;
        }

        $nome = empty($data['Riferimento']) ? $externalId : $this->clean($data['Riferimento']);

        $listing = new NormalizedListing($externalId);
        $listing->nome = $nome;
        $listing->externalModifiedAt = $this->date($data['DataModifica'] ?? '');
        $listing->createdAt = $this->date($data['DataInserimento'] ?? '');

        $listing->set('nome', $nome);

        // Classificazione: i codici nativi Getrix vengono risolti negli id delle
        // tassonomie canoniche (mappa via colonna getrix_id). La macrotipologia è
        // derivata dal FeedSyncService dalla tipologia canonica.
        $listing->set('categoria_id', (string) Taxonomy::idByProviderCode(Categoria::class, self::KEY, (string) ($data['Categoria'] ?? '')));
        $listing->set('macrotipologia_id', '');
        $listing->set('tipologia_id', (string) Taxonomy::idByProviderCode(Tipologia::class, self::KEY, $tipologiaId));

        // Localizzazione (comune risolto all'id canonico via cod_catastale/getrix_id)
        $listing->set('comune_id', (string) Taxonomy::idByProviderCode(Comune::class, self::KEY, (string) ($data['CodiceComune'] ?? '')));
        $listing->set('quartiere', $this->quartiere($data));
        $listing->set('quartiere_zona', $this->clean($data['QuartiereZona'] ?? ''));
        $listing->set('zona', $this->clean($data['Zona'] ?? ''));
        $listing->set('strada', $this->clean($data['Strada'] ?? ''));
        $listing->set('indirizzo', $this->clean($data['Indirizzo'] ?? ''));
        $listing->set('civico', (string) ($data['Civico'] ?? ''));
        $listing->set('cap', (string) ($data['Cap'] ?? ''));
        $listing->set('latitudine', (string) ($data['Latitudine'] ?? ''));
        $listing->set('longitudine', (string) ($data['Longitudine'] ?? ''));
        $listing->set('zoom', (string) ($data['Zoom'] ?? ''));
        $listing->set('pub_civico', (string) ($data['PubblicaCivico'] ?? ''));
        $listing->set('pub_indirizzo', (string) ($data['PubblicaIndirizzo'] ?? ''));
        $listing->set('pub_mappa', (string) ($data['PubblicaMappa'] ?? ''));

        // Contratto / commerciale
        $listing->set('contratto_id', (string) ($data['Contratto'] ?? ''));
        $listing->set('durata_contratto_id', (string) ($data['DurataContratto'] ?? ''));
        $listing->set('situazione_id', (string) ($data['SituazioneImmobile'] ?? ''));
        $listing->set('tipo_proprieta_id', (string) ($data['TipoProprieta'] ?? ''));
        $listing->set('n_locali', (string) ($data['NrLocali'] ?? ''));
        $listing->set('prezzo', $this->int($data['Prezzo'] ?? ''));
        $listing->set('trattativa_riservata', (string) ($data['TrattativaRiservata'] ?? ''));
        $listing->set('asta', (string) ($data['Asta'] ?? ''));
        $listing->set('pregio', (string) ($data['Pregio'] ?? ''));
        $listing->set('reddito', (string) ($data['Reddito'] ?? ''));
        $listing->set('superficie', $this->int($data['MQSuperficie'] ?? ''));
        $listing->set('spese_mensili', (string) ($data['SpeseMensili'] ?? ''));
        $listing->set('spese_id', (string) ($data['TipoSpese'] ?? ''));

        // Media (array di URL, colonne JSON: si scartano i valori vuoti)
        $listing->set('youtube', $this->urlList([
            $this->youtube($data['IDYouTube1'] ?? ''),
            $this->youtube($data['IDYouTube2'] ?? ''),
            $this->youtube($data['IDYouTube3'] ?? ''),
            $this->youtube($data['IDYouTube4'] ?? ''),
        ]));
        $listing->set('planimetria', $this->urlList([$data['URLPlanimetria'] ?? '']));
        $listing->set('virtual_tour', $this->urlList([$data['URLVirtualTour'] ?? '']));
        $listing->set('visual_tour', $this->urlList([$data['URLVisualTour'] ?? '']));
        $listing->set('video', $this->urlList([$data['URLVideo'] ?? '']));

        // Blocco di dettaglio per categoria (Residenziale/Commerciale/…): i campi
        // noti diventano colonne, l'intero blocco è conservato in `attributi`.
        $info = $this->categoryBlock($data);

        $listing->set('n_camere', (string) ($info['NrCamereLetto'] ?? ''));
        $listing->set('n_bagni', (string) ($info['NrBagni'] ?? ''));
        $listing->set('anno_costruzione', (string) ($info['AnnoCostruzione'] ?? ''));
        $listing->set('cucina_id', (string) ($info['Cucina'] ?? ''));
        $listing->set('piano', (string) ($info['Piano'] ?? ''));
        $listing->set('piani_edificio', (string) ($info['PianiEdificio'] ?? ''));
        $listing->set('n_terrazzi', (string) ($info['NrTerrazzi'] ?? ''));
        $listing->set('n_balconi', (string) ($info['NrBalconi'] ?? ''));
        $listing->set('taverna_id', (string) ($info['Taverna'] ?? ''));
        $listing->set('n_posti_auto', (string) ($info['NrPostiAuto'] ?? ''));
        $listing->set('giardino_condominiale', (string) ($info['GiardinoCondominiale'] ?? ''));
        $listing->set('giardino_privato_id', (string) ($info['GiardinoPrivato'] ?? ''));
        $listing->set('legge_classe_energetica_id', (string) ($info['LeggeClasseEnergetica'] ?? ''));
        $listing->set('classe_energetica', (string) ($info['ClasseEnergetica'] ?? ''));
        $listing->set('ipe', (string) ($info['IPE'] ?? ''));

        foreach ($info as $attrKey => $attrValue) {
            if (!is_array($attrValue)) {
                $listing->attribute((string) $attrKey, $attrValue);
            }
        }

        $this->collectImages($listing, $data);
        $this->collectDescriptions($listing, $data);

        return $listing;
    }

    private function downloadAndParse(FeedSourceConfig $feed, string $code): SimpleXMLElement
    {
        [$archiveDirectory, $displayDirectory] = $this->archiveDirectory($feed);

        if (!is_dir($archiveDirectory)
            && !mkdir($archiveDirectory, 0775, true)
            && !is_dir($archiveDirectory)
        ) {
            throw new RuntimeException('Impossibile creare la cartella di archivio del feed Getrix.');
        }

        $zipPath = $archiveDirectory.'/feed.zip';
        $xmlPath = $archiveDirectory.'/feed.xml';
        $manifestPath = $archiveDirectory.'/metadata.json';
        $sourceUrl = rtrim($this->listingsEndpoint, '/').'/'.rawurlencode($code).'.zip';

        // Il path è disponibile subito: anche un archivio non valido rimane
        // rintracciabile nello storico della sincronizzazione.
        $this->lastArtifactPath = $displayDirectory.'/feed.zip';

        $downloadError = '';
        set_error_handler(static function (int $severity, string $message) use (&$downloadError): bool {
            $downloadError = $message;

            return true;
        });

        try {
            $downloaded = copy($sourceUrl, $zipPath);
        } finally {
            restore_error_handler();
        }

        if (!$downloaded || !is_file($zipPath) || filesize($zipPath) === 0) {
            $detail = $downloadError !== '' ? ': '.$downloadError : '';
            throw new RuntimeException('Download del feed Getrix non riuscito'.$detail);
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            throw new RuntimeException('Il file Getrix scaricato non è uno ZIP valido (codice '.$opened.').');
        }

        $xmlEntryIndex = $this->xmlEntryIndex($zip, $code);
        if ($xmlEntryIndex === null) {
            $zip->close();
            throw new RuntimeException('Lo ZIP Getrix non contiene alcun file XML.');
        }

        $xmlEntry = (string) $zip->getNameIndex($xmlEntryIndex);
        $xmlContents = $zip->getFromIndex($xmlEntryIndex);
        $zip->close();

        if (!is_string($xmlContents) || $xmlContents === '') {
            throw new RuntimeException('Il file XML contenuto nello ZIP Getrix è vuoto o illeggibile.');
        }

        if (file_put_contents($xmlPath, $xmlContents, LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare il file XML estratto dal feed Getrix.');
        }

        $this->writeManifest($manifestPath, [
            'provider'       => self::KEY,
            'feed_source_id' => $feed->id,
            'downloaded_at'  => date(DATE_ATOM),
            'source'         => $sourceUrl,
            'zip'            => [
                'file'   => 'feed.zip',
                'bytes'  => filesize($zipPath),
                'sha256' => hash_file('sha256', $zipPath),
            ],
            'xml'            => [
                'file'      => 'feed.xml',
                'zip_entry' => $xmlEntry,
                'bytes'     => strlen($xmlContents),
                'sha256'    => hash('sha256', $xmlContents),
            ],
        ]);

        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $xml = simplexml_load_string($xmlContents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        if (!$xml instanceof SimpleXMLElement) {
            $detail = isset($errors[0]) ? trim((string) $errors[0]->message) : 'errore sconosciuto';
            throw new RuntimeException('XML Getrix non valido: '.$detail);
        }

        return $xml;
    }

    /** @return array{0:string, 1:string} */
    private function archiveDirectory(FeedSourceConfig $feed): array
    {
        $suffix = date('Y-m-d_H-i-s').'-'.bin2hex(random_bytes(4));
        $feedDirectory = 'feed-'.$feed->id.'/'.date('Y/m/d').'/'.$suffix;

        if ($this->archiveRoot !== null) {
            $root = rtrim($this->archiveRoot, '/');
            $directory = $root.'/'.$feedDirectory;

            return [$directory, $directory];
        }

        $runtimeRoot = defined('ROOT') ? (string) ROOT : getcwd();
        $relative = 'storage/immobili/feed-sync/getrix/'.$feedDirectory;

        return [rtrim($runtimeRoot, '/').'/'.$relative, $relative];
    }

    private function xmlEntryIndex(\ZipArchive $zip, string $code): ?int
    {
        $fallback = null;
        $preferredName = strtolower($code.'.xml');

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (!str_ends_with(strtolower($name), '.xml')) {
                continue;
            }

            if (strtolower(basename($name)) === $preferredName) {
                return $index;
            }

            $fallback ??= $index;
        }

        return $fallback;
    }

    /** @param array<string, mixed> $metadata */
    private function writeManifest(string $path, array $metadata): void
    {
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!is_string($json) || file_put_contents($path, $json."\n", LOCK_EX) === false) {
            throw new RuntimeException('Impossibile salvare i metadati del feed Getrix.');
        }
    }

    /** @param array<string, mixed> $data */
    private function categoryBlock(array $data): array
    {
        foreach (['Residenziale', 'Commerciale', 'Attivita', 'Terreno', 'Vacanze'] as $section) {
            if (isset($data[$section]) && is_array($data[$section])) {
                return $data[$section];
            }
        }

        return [];
    }

    /** @param array<string, mixed> $data */
    private function quartiere(array $data): string
    {
        if (!isset($data['Quartiere'])) {
            return '';
        }

        $q = $data['Quartiere'];

        if (is_array($q)) {
            $code = $q['@attributes']['CodiceQuartiere'] ?? '';
            if ($code !== '') {
                $row = Taxonomy::byProviderCode(Quartiere::class, self::KEY, (string) $code);
                if (is_array($row) && isset($row['nome'])) {
                    return (string) $row['nome'];
                }
            }

            return '';
        }

        return $this->clean($q);
    }

    /** @param array<string, mixed> $data */
    private function collectImages(NormalizedListing $listing, array $data): void
    {
        $immagini = $data['Immagini']['Immagine'] ?? [];

        if ($immagini === []) {
            return;
        }

        // Normalizza a lista (una sola immagine arriva come mappa singola).
        if (isset($immagini['@attributes']) || isset($immagini['URL'])) {
            $immagini = [$immagini];
        }

        foreach ($immagini as $immagine) {
            if (!is_array($immagine)) {
                continue;
            }

            $attrs = $immagine['@attributes'] ?? [];
            $type = (string) ($attrs['Tipo'] ?? 'F');
            $url = (string) ($immagine['URL'] ?? '');

            // Massima risoluzione scaricabile: il CDN Getrix vieta `xxxl`
            // (403 "Request forbidden by administrative rules"); `xxl` (~1575px)
            // è la variante più grande servita.
            $source = $url;
            if ($type === 'F' && str_contains($url, '/m.jpg')) {
                $source = str_replace('/m.jpg', '/xxl.jpg', $url);
            }

            $listing->addImage([
                'external_id' => (string) ($attrs['IDImmagine'] ?? ''),
                'tipo'        => $type,
                'planimetria' => $type === 'P' ? 'true' : 'false',
                'position'    => (string) ($immagine['Posizione'] ?? ''),
                'titolo'      => (isset($immagine['Titolo']) && !is_array($immagine['Titolo'])) ? $this->clean($immagine['Titolo']) : '',
                'source_url'  => $source,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function collectDescriptions(NormalizedListing $listing, array $data): void
    {
        $descrizioni = $data['Descrizioni']['Descrizione'] ?? null;

        if ($descrizioni === null) {
            return;
        }

        // Una sola descrizione arriva come mappa singola.
        if (!isset($descrizioni[0])) {
            $descrizioni = [$descrizioni];
        }

        foreach ($descrizioni as $descrizione) {
            if (!is_array($descrizione)) {
                continue;
            }

            $listing->addDescription([
                'lingua'      => strtolower((string) ($descrizione['@attributes']['Lingua'] ?? 'it')),
                'titolo'      => (isset($descrizione['Titolo']) && !is_array($descrizione['Titolo'])) ? $this->clean($descrizione['Titolo']) : '',
                'testo'       => (isset($descrizione['Testo']) && !is_array($descrizione['Testo'])) ? $this->clean($descrizione['Testo']) : '',
                'testo_breve' => (isset($descrizione['TestoBreve']) && !is_array($descrizione['TestoBreve'])) ? $this->clean($descrizione['TestoBreve']) : '',
            ]);
        }
    }

    // ------------------------------------------------------------- Tassonomie

    /**
     * Codici categoria Getrix → chiave canonica del modulo.
     *
     * @var array<string, string>
     */
    private const CATEGORY_KEYS = [
        '1' => 'residenziale',
        '2' => 'commerciale',
        '3' => 'attivita',
        '4' => 'vacanze',
        '5' => 'terreno',
    ];

    public function syncTaxonomies(FeedSourceConfig $feed): void
    {
        $force = function_exists('immobiliIsTrue')
            && immobiliIsTrue($_REQUEST['refresh_taxonomies'] ?? '');

        // Getrix semina le tabelle canoniche: upsert per chiave naturale
        // (cod_catastale, sigla, slug) riempiendo getrix_id, senza cancellare le
        // mappe degli altri gestionali. Se il set base esiste già lo riusa; il
        // refresh resta disponibile con ?refresh_taxonomies=1.
        if (!$force && $this->hasBaseTaxonomies()) {
            return;
        }

        $import = new Import();

        $this->syncCategorie($import);
        $this->syncComuni($import);
        $this->syncQuartieri($import);
    }

    private function hasBaseTaxonomies(): bool
    {
        foreach ([Categoria::class, Tipologia::class, Comune::class] as $model) {
            $row = $model::find([], 1);

            if (!is_array($row) || !isset($row['id'])) {
                return false;
            }
        }

        return true;
    }

    private function syncCategorie(Import $import): void
    {
        foreach ($import->Categorie() as $categoria) {
            $code = (string) ($categoria['Categoria'] ?? '');
            $chiave = self::CATEGORY_KEYS[$code] ?? ('categoria-'.$code);

            $categoriaId = $this->upsert(Categoria::class, ['chiave' => $chiave], [
                'chiave'    => $chiave,
                'nome'      => $this->clean($categoria['NomeCategoria'] ?? ''),
                'getrix_id' => $code,
            ]);

            $macro = $categoria['MacroTipologie']['MacroTipologia'] ?? [];
            if (isset($macro['IDTipologiaMacro'])) {
                $macro = [$macro];
            }

            foreach ($macro as $macrotipologia) {
                $macroCode = (string) ($macrotipologia['IDTipologiaMacro'] ?? '');
                $macroNome = $this->clean($macrotipologia['TipologiaMacro'] ?? '');
                $macroChiave = $this->chiave($macroNome, 'macro-'.$macroCode);

                $macroId = $this->upsert(Macrotipologia::class, ['chiave' => $macroChiave], [
                    'chiave'       => $macroChiave,
                    'nome'         => $macroNome,
                    'categoria_id' => $categoriaId,
                    'getrix_id'    => $macroCode,
                ]);

                $tipologie = $macrotipologia['Tipologie']['Tipologia'] ?? [];
                if (isset($tipologie['IDTipologia'])) {
                    $tipologie = [$tipologie];
                }

                foreach ($tipologie as $tipologia) {
                    $tipCode = (string) ($tipologia['IDTipologia'] ?? '');
                    $tipNome = $this->clean($tipologia['Tipologia'] ?? '');
                    $tipChiave = $this->chiave($tipNome, 'tipologia-'.$tipCode);

                    $this->upsert(Tipologia::class, ['chiave' => $tipChiave], [
                        'chiave'            => $tipChiave,
                        'nome'              => $tipNome,
                        'categoria_id'      => $categoriaId,
                        'macrotipologia_id' => $macroId,
                        'getrix_id'         => $tipCode,
                    ]);
                }
            }
        }
    }

    private function syncComuni(Import $import): void
    {
        foreach ($import->Comuni() as $comune) {
            $comune = $comune['@attributes'] ?? $comune;

            $regioneNome = $this->clean($comune['Regione'] ?? '');
            $regioneId = 0;
            if ($regioneNome !== '') {
                $regioneChiave = $this->chiave($regioneNome, 'regione');
                $regioneId = $this->upsert(Regione::class, ['chiave' => $regioneChiave], [
                    'chiave'    => $regioneChiave,
                    'nome'      => $regioneNome,
                    'getrix_id' => (string) ($comune['IDRegione'] ?? ''),
                ]);
            }

            $provinciaSigla = strtoupper((string) ($comune['SiglaProvincia'] ?? ''));
            $provinciaId = 0;
            if ($provinciaSigla !== '') {
                $provinciaId = $this->upsert(Provincia::class, ['sigla' => $provinciaSigla], [
                    'sigla'      => $provinciaSigla,
                    'nome'       => $this->clean($comune['Provincia'] ?? ''),
                    'regione_id' => $regioneId,
                    'getrix_id'  => (string) ($comune['IDProvincia'] ?? ''),
                ]);
            }

            $catastale = strtoupper((string) ($comune['CodiceCatastale'] ?? ''));
            if ($catastale === '') {
                continue;
            }

            $this->upsert(Comune::class, ['cod_catastale' => $catastale], [
                'cod_catastale' => $catastale,
                'nome'          => $this->clean($comune['Comune'] ?? ''),
                'regione_id'    => $regioneId,
                'provincia_id'  => $provinciaId,
                'cap'           => (string) ($comune['CAP'] ?? ''),
                'capoluogo'     => strtolower((string) ($comune['Capoluogo'] ?? '')),
                'latitudine'    => (string) ($comune['Latitudine'] ?? ''),
                'longitudine'   => (string) ($comune['Longitudine'] ?? ''),
                'getrix_id'     => (string) ($comune['CodiceComune'] ?? ''),
            ]);
        }
    }

    private function syncQuartieri(Import $import): void
    {
        foreach ($import->Quartieri() as $quartiere) {
            $zone = $quartiere['QuartieriZone']['QuartieraZona'] ?? [];
            $attrs = $quartiere['@attributes'] ?? [];

            if ((string) ($attrs['CodiceNazione'] ?? '') !== 'IT') {
                continue;
            }

            $comune = Taxonomy::byProviderCode(Comune::class, self::KEY, (string) ($attrs['CodiceComune'] ?? ''));

            if (!is_array($comune) || !isset($comune['id'])) {
                continue;
            }

            $comuneId = (int) $comune['id'];
            $nome = $this->clean($attrs['Quartiere'] ?? '');

            if ($nome === '') {
                continue;
            }

            $quartiereId = $this->upsert(Quartiere::class, ['comune_id' => $comuneId, 'nome' => $nome], [
                'nome'         => $nome,
                'comune_id'    => $comuneId,
                'regione_id'   => (int) ($comune['regione_id'] ?? 0),
                'provincia_id' => (int) ($comune['provincia_id'] ?? 0),
                'getrix_id'    => (string) ($attrs['CodiceQuartiere'] ?? ''),
            ]);

            if (isset($zone['@attributes']) || isset($zone['CodiceQuartiereZona'])) {
                $zone = [$zone];
            }

            foreach ($zone as $zona) {
                $zona = $zona['@attributes'] ?? $zona;
                $zonaNome = $this->clean($zona['QuartiereZona'] ?? '');

                if ($zonaNome === '') {
                    continue;
                }

                $this->upsert(QuartiereZona::class, ['quartiere_id' => $quartiereId, 'nome' => $zonaNome], [
                    'nome'         => $zonaNome,
                    'quartiere_id' => $quartiereId,
                    'comune_id'    => $comuneId,
                    'getrix_id'    => (string) ($zona['CodiceQuartiereZona'] ?? ''),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------ Utils

    /**
     * Upsert canonico: cerca per chiave naturale ($match); se esiste aggiorna
     * solo i campi forniti (preserva le mappe degli altri gestionali),
     * altrimenti crea. Ritorna l'id canonico.
     *
     * @param class-string $model
     * @param array<string, mixed> $match
     * @param array<string, mixed> $data
     */
    private function upsert(string $model, array $match, array $data): int
    {
        $existing = $model::find($match, 1);

        if (is_array($existing) && isset($existing['id'])) {
            $id = (int) $existing['id'];
            $model::update($data, $id);

            return $id;
        }

        $result = $model::create($data);

        return (int) ($result->insert_id ?? ($result->id ?? 0));
    }

    /** Chiave canonica (slug del nome) con fallback se il nome è vuoto. */
    private function chiave(string $nome, string $fallback): string
    {
        $nome = trim($nome);

        return $nome === '' ? $fallback : Slug::base([$nome]);
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
        if ($value === '' || $value === null || !is_numeric($value)) {
            return '';
        }

        return (string) (int) round((float) $value);
    }

    private function youtube(mixed $id): string
    {
        $id = trim((string) $id);

        return $id === '' ? '' : 'https://www.youtube.com/embed/'.$id;
    }

    /**
     * Normalizza una lista di URL in un array di stringhe non vuote, senza
     * duplicati e reindicizzato. Usato per le colonne media JSON dell'immobile.
     *
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function urlList(array $values): array
    {
        $urls = [];

        foreach ($values as $value) {
            $url = trim((string) $value);
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
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
