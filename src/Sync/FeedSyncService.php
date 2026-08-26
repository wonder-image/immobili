<?php

namespace Wonder\Plugin\Immobili\Sync;

use Wonder\Plugin\Immobili\Feed\FeedSourceConfig;
use Wonder\Plugin\Immobili\Feed\NormalizedListing;
use Wonder\Plugin\Immobili\Feed\ProviderRegistry;
use Wonder\Plugin\Immobili\Feed\Contracts\ArchivesFeedArtifact;
use Wonder\Plugin\Immobili\Models\System\FeedSource;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\ImmobileDescrizione;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Models\System\SyncLog;
use Wonder\Plugin\Immobili\Models\Taxonomy\Tipologia;
use Wonder\Plugin\Immobili\Support\Slug;
use Wonder\Plugin\Immobili\Support\Taxonomy;

/**
 * Orchestrazione della sincronizzazione dei feed.
 *
 * Per ogni feed: sincronizza le tassonomie, marca gli immobili come "non più
 * presenti", legge il feed dal provider e fa l'upsert idempotente (preservando i
 * flag manuali evidence/visible/sold), aggiorna immagini e descrizioni, genera
 * slug e QR code (l'URL è derivato in lettura, non persistito), rimuove gli
 * immobili spariti e registra lo stato.
 */
final class FeedSyncService
{
    private int $imagesCount = 0;
    private string $currentSource = '';
    private ?ImmobilePresenter $presenter = null;

    /**
     * Sincronizza tutti i feed attivi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function syncAll(): array
    {
        $results = [];

        foreach ($this->rows(FeedSource::find(['active' => 'true', 'deleted' => 'false'])) as $row) {
            $results[] = $this->sync(FeedSourceConfig::fromRow($row));
        }

        return $results;
    }

    /**
     * Sincronizza un singolo feed per id.
     *
     * @return array<string, mixed>
     */
    public function syncById(int|string $id): array
    {
        $row = FeedSource::findById($id);

        if (!is_array($row) || !isset($row['id'])) {
            return ['success' => false, 'count' => 0, 'message' => 'Feed non trovato'];
        }

        return $this->sync(FeedSourceConfig::fromRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    public function sync(FeedSourceConfig $feed): array
    {
        ProviderRegistry::registerDefaults();
        $provider = ProviderRegistry::get($feed->provider);

        $this->imagesCount = 0;
        $this->currentSource = $this->sourceFile($feed);

        if ($provider === null) {
            return $this->finish($feed, false, 0, 'Provider non disponibile: '.$feed->provider);
        }

        try {
            $provider->syncTaxonomies($feed);
        } catch (\Throwable) {
            // Le tassonomie non bloccano l'import degli immobili.
        }

        // Prima scarica, valida e normalizza l'intero feed. In questo modo un
        // download fallito o un parser che produce zero righe non marca gli
        // immobili esistenti come spariti e non può svuotare il catalogo.
        try {
            $listings = [];

            foreach ($provider->fetchListings($feed) as $listing) {
                if (!$listing instanceof NormalizedListing) {
                    throw new \RuntimeException('Il provider ha restituito una riga non valida.');
                }

                $listings[] = $listing;
            }

            $this->captureArtifact($provider);

            if ($listings === []) {
                throw new \RuntimeException(
                    'Il feed non contiene immobili importabili; sincronizzazione annullata senza modificare il catalogo.'
                );
            }
        } catch (\Throwable $e) {
            $this->captureArtifact($provider);

            return $this->finish($feed, false, 0, $e->getMessage());
        }

        // Solo dopo la validazione marca gli immobili come non più presenti:
        // quelli incontrati verranno riaccesi, gli altri rimossi a fine sync.
        Immobile::query()->Update(Immobile::$table, ['feed_deleted' => 'true'], 'feed_source_id', $feed->id);

        $count = 0;

        try {
            foreach ($listings as $listing) {
                $this->upsert($feed, $listing);
                $count++;
            }
        } catch (\Throwable $e) {
            return $this->finish($feed, false, $count, $e->getMessage());
        }

        $this->removeVanished($feed);

        return $this->finish($feed, true, $count, '');
    }

    private function upsert(FeedSourceConfig $feed, NormalizedListing $listing): void
    {
        $existing = Immobile::find([
            'provider'       => $feed->provider,
            'feed_source_id' => $feed->id,
            'external_id'    => $listing->externalId,
        ], 1);
        $existing = (is_array($existing) && isset($existing['id'])) ? $existing : null;

        // Se già presente e non più recente nel feed: aggiorno solo lo stato.
        if ($existing !== null) {
            $oldMod = (int) strtotime((string) ($existing['external_modified_at'] ?? ''));
            $newMod = (int) strtotime($listing->externalModifiedAt);

            if ($newMod > 0 && $newMod <= $oldMod) {
                Immobile::update([
                    'feed_deleted' => 'false',
                    'synced_at'    => date('Y-m-d H:i:s'),
                ], (int) $existing['id']);

                return;
            }
        }

        $fields = $listing->fields;
        $fields['provider'] = $feed->provider;
        $fields['feed_source_id'] = $feed->id;
        $fields['external_id'] = $listing->externalId;
        $fields['external_modified_at'] = $listing->externalModifiedAt;
        $fields['synced_at'] = date('Y-m-d H:i:s');
        $fields['feed_deleted'] = 'false';
        $fields['attributi'] = $listing->attributi;

        // Flag di pubblicazione.
        // - visible: sempre manuale (default feed alla creazione, poi preservato).
        // - evidence: manuale, ma un provider può fornire il default iniziale.
        // - sold: se il provider lo fornisce (es. Gestim) è autoritativo dal feed
        //   anche in update; altrimenti manuale (default alla creazione, preservato).
        if ($existing !== null) {
            $fields['evidence'] = (string) ($existing['evidence'] ?? 'false');
            $fields['visible']  = (string) ($existing['visible'] ?? 'true');
            $fields['sold']     = $listing->sold ?? (string) ($existing['sold'] ?? 'false');
        } else {
            $fields['evidence'] = $listing->evidence
                ?? (immobiliIsTrue($feed->default_evidence) ? 'true' : 'false');
            $fields['visible']  = immobiliIsTrue($feed->default_visible) ? 'true' : 'false';
            $fields['sold']     = $listing->sold
                ?? (immobiliIsTrue($feed->default_sold) ? 'true' : 'false');
        }

        // Slug pubblico leggibile (senza external_id), stabile e univoco.
        $fields['slug'] = $this->buildSlug($feed, $listing, $existing);

        // Macrotipologia derivata dalla tassonomia Tipologia: il feed dell'immobile
        // porta solo la tipologia, la macro si risolve dalla tassonomia importata.
        $fields['macrotipologia_id'] = $this->macrotipologiaId(
            (string) ($fields['tipologia_id'] ?? ''),
            (string) ($fields['macrotipologia_id'] ?? '')
        );

        // Campi derivati denormalizzati per la ricerca SQL della lista frontend.
        $fields = array_merge($fields, ($this->presenter ??= new ImmobilePresenter())->searchFields($fields));

        // FK tassonomia non risolte (0/vuote) → rimosse: l'insert le lascia NULL,
        // senza violare il vincolo di chiave esterna (l'id 0 non esiste).
        foreach (['categoria_id', 'macrotipologia_id', 'tipologia_id', 'comune_id', 'quartiere_id', 'quartiere_zona_id'] as $fk) {
            if (!isset($fields[$fk]) || (int) $fields[$fk] <= 0) {
                unset($fields[$fk]);
            }
        }

        if ($existing !== null) {
            $id = (int) $existing['id'];
            Immobile::update($fields, $id);
            ImmobileImmagine::query()->Delete(ImmobileImmagine::$table, ['immobile_id' => $id]);
            ImmobileDescrizione::query()->Delete(ImmobileDescrizione::$table, ['immobile_id' => $id]);
        } else {
            $result = Immobile::create($fields);
            $id = (int) ($result->insert_id ?? ($result->id ?? 0));
        }

        if ($id <= 0) {
            throw new \RuntimeException('Salvataggio fallito per l’immobile Getrix '.$listing->externalId.'.');
        }

        // Il QR viene generato al sync (PNG su disco), ma il suo URL NON è salvato
        // in DB: in lettura si ricostruisce dall'external_id (Immobile::decorate()).
        $this->buildQrCode($listing, $this->buildUrl((string) $fields['slug']));

        // Le immagini vengono registrate solo con la source_url a massima
        // risoluzione: il download e la generazione delle varianti responsive
        // avvengono nel secondo piano (ImageProcessor).
        foreach ($listing->images as $image) {
            ImmobileImmagine::create([
                'immobile_id' => $id,
                'external_id' => (string) ($image['external_id'] ?? ''),
                'tipo'        => (string) ($image['tipo'] ?? 'F'),
                'planimetria' => (string) ($image['planimetria'] ?? 'false'),
                'position'    => (string) ($image['position'] ?? ''),
                'titolo'      => (string) ($image['titolo'] ?? ''),
                'source_url'  => (string) ($image['source_url'] ?? ''),
                'file'        => '',
                'resized'     => 'false',
            ]);

            $this->imagesCount++;
        }

        foreach ($listing->descriptions as $descrizione) {
            ImmobileDescrizione::create([
                'immobile_id' => $id,
                'lingua'      => (string) ($descrizione['lingua'] ?? 'it'),
                'titolo'      => (string) ($descrizione['titolo'] ?? ''),
                'testo'       => (string) ($descrizione['testo'] ?? ''),
                'testo_breve' => (string) ($descrizione['testo_breve'] ?? ''),
            ]);
        }
    }

    private function removeVanished(FeedSourceConfig $feed): void
    {
        $gone = Immobile::find([
            'feed_source_id' => $feed->id,
            'feed_deleted'   => 'true',
        ]);

        foreach ($this->rows($gone) as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            ImmobileImmagine::query()->Delete(ImmobileImmagine::$table, ['immobile_id' => $id]);
            ImmobileDescrizione::query()->Delete(ImmobileDescrizione::$table, ['immobile_id' => $id]);
            Immobile::delete($id);
        }
    }

    /**
     * Slug pubblico leggibile (tipologia + indirizzo + comune), senza external_id.
     * Per un immobile già esistente riusa lo slug salvato (stabile tra i sync);
     * per uno nuovo genera una base leggibile resa univoca con suffisso numerico.
     *
     * @param array<string, mixed>|null $existing
     */
    private function buildSlug(FeedSourceConfig $feed, NormalizedListing $listing, ?array $existing): string
    {
        // Slug stabile: se l'immobile esiste già e ne ha uno, non lo si ricalcola.
        if ($existing !== null && trim((string) ($existing['slug'] ?? '')) !== '') {
            return (string) $existing['slug'];
        }

        $tipologia = Taxonomy::tipologiaNomeById((int) ($listing->fields['tipologia_id'] ?? 0));
        if ($tipologia === '') {
            $tipologia = (string) ($listing->attributi['tipologia'] ?? '');
        }

        $comune = Taxonomy::comuneNomeById((int) ($listing->fields['comune_id'] ?? 0));
        if ($comune === '') {
            $comune = (string) ($listing->attributi['comune'] ?? '');
        }

        return Slug::fromRow([
            'tipologia_nome' => $tipologia,
            'strada'         => (string) ($listing->fields['strada'] ?? ''),
            'indirizzo'      => (string) ($listing->fields['indirizzo'] ?? ''),
            'comune_nome'    => $comune,
        ], $existing !== null ? (int) $existing['id'] : null);
    }

    /**
     * Macrotipologia di un immobile: risolta dalla tassonomia Tipologia (che
     * porta il codice macro). Preserva un eventuale valore già fornito dal
     * provider; '' se non risolvibile.
     */
    private function macrotipologiaId(string $tipologiaId, string $current): string
    {
        if ($current !== '' && $current !== '0') {
            return $current;
        }

        $id = (int) $tipologiaId;

        if ($id <= 0) {
            return '';
        }

        $tipologia = Taxonomy::byId(Tipologia::class, $id);

        return (string) ($tipologia['macrotipologia_id'] ?? '');
    }

    private function buildUrl(string $slug): string
    {
        $path = $GLOBALS['PATH'] ?? null;
        $site = is_object($path) ? rtrim((string) ($path->site ?? ''), '/') : '';

        return $site.'/immobili/'.$slug.'/';
    }

    /**
     * Genera al sync il PNG del QR code nella posizione canonica
     * (upload/immobili/{external_id}/qrcode.png). Il percorso NON viene salvato
     * in DB: la lettura lo ricostruisce con immobiliQrCodeUrl().
     */
    private function buildQrCode(NormalizedListing $listing, string $url): void
    {
        if (!function_exists('createQrCode') || $url === '') {
            return;
        }

        $file = immobiliQrCodeFile($listing->externalId);

        if ($file === null) {
            return;
        }

        $dir = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            createQrCode($url, $file);
        } catch (\Throwable) {
            // Il QR non è essenziale: un errore non blocca la sincronizzazione.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function finish(FeedSourceConfig $feed, bool $success, int $count, string $message): array
    {
        $status = $success
            ? 'OK - '.$count.' immobili - '.date('Y-m-d H:i')
            : 'ERRORE - '.$message;

        if ($feed->id > 0) {
            // Update tecnico parziale: Model::update() validerebbe anche i
            // campi required non presenti (es. name) e scarterebbe lo stato.
            FeedSource::query()->Update(
                FeedSource::$table,
                [
                    'last_sync_at'     => date('Y-m-d H:i:s'),
                    'last_sync_status' => $status,
                ],
                'id',
                $feed->id,
            );
        }

        // Storico: file/sorgente importato + conteggi + esito.
        SyncLog::create([
            'feed_source_id' => $feed->id,
            'provider'       => $feed->provider,
            'kind'           => 'sync',
            'source'         => $this->currentSource,
            'immobili_count' => $count,
            'images_count'   => $this->imagesCount,
            'status'         => $success ? 'ok' : 'error',
            'message'        => $message,
        ]);

        return [
            'success' => $success,
            'count'   => $count,
            'message' => $message,
            'feed'    => $feed->id,
            'source'  => $this->currentSource,
        ];
    }

    private function captureArtifact(object $provider): void
    {
        if (!$provider instanceof ArchivesFeedArtifact) {
            return;
        }

        $artifact = trim($provider->lastArtifactPath());
        if ($artifact !== '') {
            $this->currentSource = $artifact;
        }
    }

    /**
     * File/sorgente del feed importato (per lo storico).
     */
    private function sourceFile(FeedSourceConfig $feed): string
    {
        return match ($feed->provider) {
            'getrix' => $feed->code !== '' ? 'https://feed.getrix.it/xml/'.$feed->code.'.zip' : '',
            'gestim' => trim((string) ($_REQUEST['callback'] ?? '')),
            default  => trim((string) ($_REQUEST['callback'] ?? '')),
        };
    }

    /**
     * Normalizza il risultato di find() (riga singola o lista) a lista.
     *
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
}
