<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Immobili\Feed\FeedSourceConfig;
use Wonder\Plugin\Immobili\Feed\NormalizedListing;
use Wonder\Plugin\Immobili\Feed\ProviderRegistry;
use Wonder\Plugin\Immobili\Models\FeedSource;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\ImmobileDescrizione;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Models\SyncLog;
use Wonder\Plugin\Immobili\Support\Taxonomy;

/**
 * Orchestrazione della sincronizzazione dei feed.
 *
 * Per ogni feed: sincronizza le tassonomie, marca gli immobili come "non più
 * presenti", legge il feed dal provider e fa l'upsert idempotente (preservando i
 * flag manuali evidence/visible/sold), aggiorna immagini e descrizioni, genera
 * slug/URL/QR code, rimuove gli immobili spariti e registra lo stato.
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

        // Marca tutti gli immobili del feed come non più presenti (verranno
        // "riaccesi" quelli ancora nel feed, gli altri rimossi a fine sync).
        Immobile::query()->Update(Immobile::$table, ['feed_deleted' => 'true'], 'feed_source_id', $feed->id);

        $count = 0;

        try {
            foreach ($provider->fetchListings($feed) as $listing) {
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

        $dir = $this->buildDir($feed, $listing);
        $fields['dir'] = $dir;
        $fields['url'] = $this->buildUrl($dir);

        // Campi derivati denormalizzati per la ricerca SQL della lista frontend.
        $fields = array_merge($fields, ($this->presenter ??= new ImmobilePresenter())->searchFields($fields));

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
            return;
        }

        $qrcode = $this->buildQrCode($feed, $listing, (string) $fields['url']);
        if ($qrcode !== '') {
            Immobile::update(['qrcode' => $qrcode], $id);
        }

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

    private function buildDir(FeedSourceConfig $feed, NormalizedListing $listing): string
    {
        $tipologia = Taxonomy::tipologiaNome($feed->provider, (string) ($listing->fields['tipologia_id'] ?? ''));
        if ($tipologia === '') {
            $tipologia = (string) ($listing->attributi['tipologia'] ?? '');
        }

        $comune = Taxonomy::comuneNome($feed->provider, (string) ($listing->fields['comune_id'] ?? ''));
        if ($comune === '') {
            $comune = (string) ($listing->attributi['comune'] ?? '');
        }

        $parts = trim(implode(' ', array_filter([
            $tipologia,
            (string) ($listing->fields['strada'] ?? ''),
            (string) ($listing->fields['indirizzo'] ?? ''),
            $comune,
        ])));

        $slug = immobiliSlug($parts) ?: 'immobile';

        // L'external id garantisce l'unicità dello slug all'interno del feed.
        return $slug.'-'.immobiliSlug($listing->externalId);
    }

    private function buildUrl(string $dir): string
    {
        $path = $GLOBALS['PATH'] ?? null;
        $site = is_object($path) ? rtrim((string) ($path->site ?? ''), '/') : '';

        return $site.'/immobili/'.$dir.'/';
    }

    private function buildQrCode(FeedSourceConfig $feed, NormalizedListing $listing, string $url): string
    {
        if (!function_exists('createQrCode') || $url === '') {
            return '';
        }

        $path = $GLOBALS['PATH'] ?? null;

        if (!is_object($path) || empty($path->rUpload)) {
            return '';
        }

        $dir = rtrim((string) $path->rUpload, '/').'/immobili/'.$listing->externalId;

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir.'/qrcode.png';

        try {
            createQrCode($url, $file);
        } catch (\Throwable) {
            return '';
        }

        if (!is_file($file)) {
            return '';
        }

        return rtrim((string) ($path->upload ?? ''), '/').'/immobili/'.$listing->externalId.'/qrcode.png';
    }

    /**
     * @return array<string, mixed>
     */
    private function finish(FeedSourceConfig $feed, bool $success, int $count, string $message): array
    {
        $status = $success
            ? 'OK · '.$count.' immobili · '.date('Y-m-d H:i')
            : 'ERRORE · '.$message;

        if ($feed->id > 0) {
            FeedSource::update([
                'last_sync_at'     => date('Y-m-d H:i:s'),
                'last_sync_status' => $status,
            ], $feed->id);
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
        ];
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
