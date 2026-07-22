<?php

namespace Wonder\Plugin\Immobili\Feed;

use RuntimeException;
use SimpleXMLElement;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Plugin\Getrix\Import;
use Wonder\Plugin\Immobili\Feed\Contracts\ArchivesFeedArtifact;
use Wonder\Plugin\Immobili\Feed\Contracts\FeedProvider;
use Wonder\Plugin\Immobili\Models\Categoria;
use Wonder\Plugin\Immobili\Models\Comune;
use Wonder\Plugin\Immobili\Models\Macrotipologia;
use Wonder\Plugin\Immobili\Models\Provincia;
use Wonder\Plugin\Immobili\Models\Quartiere;
use Wonder\Plugin\Immobili\Models\QuartiereZona;
use Wonder\Plugin\Immobili\Models\Regione;
use Wonder\Plugin\Immobili\Models\Tipologia;

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

        // Classificazione (si conservano i codici nativi; la risoluzione avviene
        // in lettura via tassonomie scoping per provider).
        $listing->set('categoria_id', (string) ($data['Categoria'] ?? ''));
        $listing->set('macrotipologia_id', '');
        $listing->set('tipologia_id', $tipologiaId);

        // Localizzazione
        $listing->set('comune_id', (string) ($data['CodiceComune'] ?? ''));
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

        // Media
        $listing->set('youtube_1', $this->youtube($data['IDYouTube1'] ?? ''));
        $listing->set('youtube_2', $this->youtube($data['IDYouTube2'] ?? ''));
        $listing->set('youtube_3', $this->youtube($data['IDYouTube3'] ?? ''));
        $listing->set('youtube_4', $this->youtube($data['IDYouTube4'] ?? ''));
        $listing->set('planimetria', (string) ($data['URLPlanimetria'] ?? ''));
        $listing->set('virtual_tour', (string) ($data['URLVirtualTour'] ?? ''));
        $listing->set('visual_tour', (string) ($data['URLVisualTour'] ?? ''));
        $listing->set('video', (string) ($data['URLVideo'] ?? ''));

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
                $row = Quartiere::find(['provider' => self::KEY, 'codice' => (string) $code], 1);
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

    public function syncTaxonomies(FeedSourceConfig $feed): void
    {
        $force = function_exists('immobiliIsTrue')
            && immobiliIsTrue($_REQUEST['refresh_taxonomies'] ?? '');

        // Le tassonomie Getrix sono globali e molto voluminose (oltre 7.000
        // comuni). Ricostruirle a ogni sync può superare il timeout HTTP prima
        // ancora di leggere gli immobili. Se il set base esiste già, lo riusa;
        // il refresh resta disponibile con ?refresh_taxonomies=1.
        if (!$force && $this->hasBaseTaxonomies()) {
            return;
        }

        $import = new Import();

        $this->clearTaxonomies();
        $this->syncCategorie($import);
        $this->syncComuni($import);
        $this->syncQuartieri($import);
    }

    private function hasBaseTaxonomies(): bool
    {
        foreach ([Categoria::class, Tipologia::class, Comune::class] as $model) {
            $row = $model::find(['provider' => self::KEY, 'deleted' => 'false'], 1);

            if (!is_array($row) || !isset($row['id'])) {
                return false;
            }
        }

        return true;
    }

    private function clearTaxonomies(): void
    {
        foreach ([
            Categoria::class, Macrotipologia::class, Tipologia::class,
            Regione::class, Provincia::class, Comune::class,
            Quartiere::class, QuartiereZona::class,
        ] as $model) {
            $rows = $model::find(['provider' => self::KEY]);
            foreach ($this->rows($rows) as $row) {
                if (isset($row['id'])) {
                    $model::delete((int) $row['id']);
                }
            }
        }
    }

    private function syncCategorie(Import $import): void
    {
        foreach ($import->Categorie() as $categoria) {
            $categoriaCode = (string) ($categoria['Categoria'] ?? '');

            Categoria::create([
                'provider' => self::KEY,
                'codice'   => $categoriaCode,
                'nome'     => $this->clean($categoria['NomeCategoria'] ?? ''),
            ]);

            $macro = $categoria['MacroTipologie']['MacroTipologia'] ?? [];
            if (isset($macro['IDTipologiaMacro'])) {
                $macro = [$macro];
            }

            foreach ($macro as $macrotipologia) {
                $macroCode = (string) ($macrotipologia['IDTipologiaMacro'] ?? '');

                Macrotipologia::create([
                    'provider'     => self::KEY,
                    'codice'       => $macroCode,
                    'categoria_id' => $categoriaCode,
                    'nome'         => $this->clean($macrotipologia['TipologiaMacro'] ?? ''),
                ]);

                $tipologie = $macrotipologia['Tipologie']['Tipologia'] ?? [];
                if (isset($tipologie['IDTipologia'])) {
                    $tipologie = [$tipologie];
                }

                foreach ($tipologie as $tipologia) {
                    Tipologia::create([
                        'provider'          => self::KEY,
                        'codice'            => (string) ($tipologia['IDTipologia'] ?? ''),
                        'categoria_id'      => $categoriaCode,
                        'macrotipologia_id' => $macroCode,
                        'nome'              => $this->clean($tipologia['Tipologia'] ?? ''),
                    ]);
                }
            }
        }
    }

    private function syncComuni(Import $import): void
    {
        foreach ($import->Comuni() as $comune) {
            $comune = $comune['@attributes'] ?? $comune;

            $regioneCode = (string) ($comune['IDRegione'] ?? '');
            if ($regioneCode !== '' && !$this->exists(Regione::class, $regioneCode)) {
                Regione::create([
                    'provider' => self::KEY,
                    'codice'   => $regioneCode,
                    'nome'     => $this->clean($comune['Regione'] ?? ''),
                ]);
            }

            $provinciaCode = (string) ($comune['IDProvincia'] ?? '');
            if ($provinciaCode !== '' && !$this->exists(Provincia::class, $provinciaCode)) {
                Provincia::create([
                    'provider'   => self::KEY,
                    'codice'     => $provinciaCode,
                    'regione_id' => $regioneCode,
                    'nome'       => $this->clean($comune['Provincia'] ?? ''),
                    'sigla'      => (string) ($comune['SiglaProvincia'] ?? ''),
                ]);
            }

            Comune::create([
                'provider'      => self::KEY,
                'codice'        => (string) ($comune['CodiceComune'] ?? ''),
                'regione_id'    => $regioneCode,
                'provincia_id'  => $provinciaCode,
                'nome'          => $this->clean($comune['Comune'] ?? ''),
                'cap'           => (string) ($comune['CAP'] ?? ''),
                'cod_catastale' => (string) ($comune['CodiceCatastale'] ?? ''),
                'capoluogo'     => strtolower((string) ($comune['Capoluogo'] ?? '')),
                'latitudine'    => (string) ($comune['Latitudine'] ?? ''),
                'longitudine'   => (string) ($comune['Longitudine'] ?? ''),
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

            $comune = Comune::find([
                'provider' => self::KEY,
                'codice'   => (string) ($attrs['CodiceComune'] ?? ''),
            ], 1);

            if (!is_array($comune) || !isset($comune['id'])) {
                continue;
            }

            $result = Quartiere::create([
                'provider'     => self::KEY,
                'codice'       => (string) ($attrs['CodiceQuartiere'] ?? ''),
                'regione_id'   => (string) ($comune['regione_id'] ?? ''),
                'provincia_id' => (string) ($comune['provincia_id'] ?? ''),
                'comune_id'    => (string) ($comune['id'] ?? ''),
                'nome'         => $this->clean($attrs['Quartiere'] ?? ''),
            ]);

            $quartiereId = (string) ($result->insert_id ?? ($result->id ?? ''));

            if (isset($zone['@attributes']) || isset($zone['CodiceQuartiereZona'])) {
                $zone = [$zone];
            }

            foreach ($zone as $zona) {
                $zona = $zona['@attributes'] ?? $zona;

                QuartiereZona::create([
                    'provider'     => self::KEY,
                    'codice'       => (string) ($zona['CodiceQuartiereZona'] ?? ''),
                    'regione_id'   => (string) ($comune['regione_id'] ?? ''),
                    'provincia_id' => (string) ($comune['provincia_id'] ?? ''),
                    'comune_id'    => (string) ($comune['id'] ?? ''),
                    'quartiere_id' => $quartiereId,
                    'nome'         => $this->clean($zona['QuartiereZona'] ?? ''),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------ Utils

    private function exists(string $model, string $codice): bool
    {
        $row = $model::find(['provider' => self::KEY, 'codice' => $codice], 1);

        return is_array($row) && isset($row['id']);
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return isset($rows['id']) ? [$rows] : array_values($rows);
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
