<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Immobili\Models\ImmobileDescrizione;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Support\Taxonomy;

/**
 * Arricchisce una riga `immobili` con i campi derivati usati dalle view
 * (prezzo/indirizzo formattati, etichette tassonomie, immagini, descrizione
 * nella lingua corrente, GeoJSON per la mappa). Provider-agnostico.
 */
final class ImmobilePresenter
{
    /**
     * Presentazione completa (dettaglio): include immagini e descrizione.
     *
     * @param array<string, mixed> $row
     */
    public function present(array $row, ?string $locale = null): object
    {
        $data = $this->base($row);

        $id = (int) ($row['id'] ?? 0);

        $images = $this->images($id);
        $data['images'] = $images['photos'];
        $data['planimetrie'] = $images['plans'];
        $data['cover'] = $images['photos'][0]['thumb'] ?? ($images['photos'][0]['url'] ?? '');

        $descrizione = $this->descrizione($id, $locale);
        $data['titolo'] = $descrizione['titolo'] !== '' ? $descrizione['titolo'] : $data['prettyName'];
        $data['descrizione'] = $descrizione['testo'];
        $data['descrizione_breve'] = $descrizione['testo_breve'];

        $data['geo_json'] = $this->geoJson($row, $data);
        $data['gmaps'] = $this->gmaps($row);

        return (object) $data;
    }

    /**
     * Presentazione leggera per la card in lista: solo cover + dati sintetici.
     *
     * @param array<string, mixed> $row
     */
    public function card(array $row): object
    {
        $data = $this->base($row);
        $data['cover'] = $this->cover((int) ($row['id'] ?? 0));
        $data['geo_json'] = $this->geoJson($row, $data);

        return (object) $data;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, object>
     */
    public function cards(array $rows): array
    {
        return array_map(fn (array $row): object => $this->card($row), array_values($rows));
    }

    /**
     * Campi base derivati comuni a card e dettaglio.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function base(array $row): array
    {
        $provider = (string) ($row['provider'] ?? '');
        $contratto = strtoupper((string) ($row['contratto_id'] ?? ''));
        $affitto = ($contratto === 'A');

        $attributi = immobiliDecodeJsonArray($row['attributi'] ?? []);

        // Etichette tassonomia con fallback sugli attributi (i provider che
        // forniscono nomi anziché codici — es. Gestim — li salvano lì).
        $tipologia = Taxonomy::tipologiaNome($provider, (string) ($row['tipologia_id'] ?? ''));
        if ($tipologia === '') {
            $tipologia = (string) ($attributi['tipologia'] ?? '');
        }

        $comune = $this->comuneName($row, $attributi);

        $dir = (string) ($row['dir'] ?? '');
        $url = (string) ($row['url'] ?? '');
        if ($url === '' && $dir !== '') {
            $url = '/immobili/'.$dir.'/';
        }

        $prezzo = immobiliIsTrue($row['trattativa_riservata'] ?? '')
            ? 'Trattativa riservata'
            : immobiliFormatPrice($row['prezzo'] ?? 0);

        if ($prezzo !== '' && $affitto && !immobiliIsTrue($row['trattativa_riservata'] ?? '')) {
            $prezzo .= '/mese';
        }

        return [
            'id'            => (int) ($row['id'] ?? 0),
            'provider'      => $provider,
            'dir'           => $dir,
            'url'           => $url,
            'nome'          => (string) ($row['nome'] ?? ''),
            'tipologia'     => $tipologia,
            'comune'        => $comune,
            'contratto'     => $affitto ? 'Affitto' : 'Vendita',
            'prezzo'        => $prezzo,
            'superficie'    => immobiliFormatSurface($row['superficie'] ?? 0),
            'locali'        => (int) ($row['n_locali'] ?? 0),
            'camere'        => (int) ($row['n_camere'] ?? 0),
            'bagni'         => (int) ($row['n_bagni'] ?? 0),
            'classe'        => strtoupper((string) ($row['classe_energetica'] ?? '')),
            'evidence'      => immobiliIsTrue($row['evidence'] ?? ''),
            'sold'          => immobiliIsTrue($row['sold'] ?? ''),
            'qrcode'        => (string) ($row['qrcode'] ?? ''),
            'latitudine'    => (string) ($row['latitudine'] ?? ''),
            'longitudine'   => (string) ($row['longitudine'] ?? ''),
            'prettyName'    => $this->prettyName($tipologia, $comune, (string) ($row['strada'] ?? '')),
            'prettyAddress' => $this->prettyAddress($row, $comune),
            'attributi'     => $attributi,
        ];
    }

    /**
     * Nome del comune di una riga immobile: etichetta tassonomia (Getrix) con
     * fallback sugli attributi (provider che forniscono nomi, es. Gestim).
     * Fonte unica usata sia dalla card sia dall'elenco dei filtri.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $attributi attributi già decodificati (opzionale)
     */
    public function comuneName(array $row, ?array $attributi = null): string
    {
        $provider = (string) ($row['provider'] ?? '');
        $comune = Taxonomy::comuneNome($provider, (string) ($row['comune_id'] ?? ''));

        if ($comune === '') {
            $attributi ??= immobiliDecodeJsonArray($row['attributi'] ?? []);
            $comune = (string) ($attributi['comune'] ?? '');
        }

        return $comune;
    }

    /**
     * Campi derivati denormalizzati usati dai filtri SQL (lista frontend):
     * nome comune e tipologia risolti (con fallback JSON Gestim) e un blob di
     * ricerca lowercase. Unica fonte del calcolo, condivisa da sync e backfill.
     *
     * @param array<string, mixed> $row  riga immobile (o campi normalizzati) con
     *   almeno: provider, tipologia_id, comune_id, attributi, nome, strada,
     *   indirizzo, civico, pub_indirizzo, pub_civico
     * @return array{comune_nome: string, tipologia_nome: string, ricerca: string}
     */
    public function searchFields(array $row): array
    {
        $provider = (string) ($row['provider'] ?? '');
        $attributi = immobiliDecodeJsonArray($row['attributi'] ?? []);

        $tipologia = Taxonomy::tipologiaNome($provider, (string) ($row['tipologia_id'] ?? ''));
        if ($tipologia === '') {
            $tipologia = (string) ($attributi['tipologia'] ?? '');
        }

        $comune = $this->comuneName($row, $attributi);

        $nome = (string) ($row['nome'] ?? '');
        $indirizzo = $this->prettyAddress($row, $comune);

        $ricerca = strtolower(trim(implode(' ', array_filter([
            $nome, $tipologia, $indirizzo,
        ]))));

        return [
            'comune_nome'    => $comune,
            'tipologia_nome' => $tipologia,
            'ricerca'        => $ricerca,
        ];
    }

    private function prettyName(string $tipologia, string $comune, string $strada): string
    {
        $left = $tipologia !== '' ? $tipologia : 'Immobile';
        $right = $strada !== '' ? $strada : $comune;

        return trim($right !== '' ? $left.' · '.$right : $left);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function prettyAddress(array $row, string $comune): string
    {
        $parts = [];

        if (immobiliIsTrue($row['pub_indirizzo'] ?? 'true')) {
            $strada = trim((string) ($row['strada'] ?? '').' '.(string) ($row['indirizzo'] ?? ''));

            if (immobiliIsTrue($row['pub_civico'] ?? '') && !empty($row['civico'])) {
                $strada = trim($strada.', '.(string) $row['civico']);
            }

            if ($strada !== '') {
                $parts[] = $strada;
            }
        }

        if ($comune !== '') {
            $parts[] = $comune;
        }

        return implode(' — ', $parts);
    }

    /**
     * @return array{photos: array<int, array<string, mixed>>, plans: array<int, array<string, mixed>>}
     */
    private function images(int $immobileId): array
    {
        $photos = [];
        $plans = [];

        if ($immobileId <= 0) {
            return ['photos' => $photos, 'plans' => $plans];
        }

        $rows = ImmobileImmagine::find(['immobile_id' => $immobileId], null, 'position', 'ASC');

        foreach ($this->rows($rows) as $image) {
            $entry = $this->imageEntry($image);

            if ($entry['url'] === '') {
                continue;
            }

            if ($entry['planimetria']) {
                $plans[] = $entry;
            } else {
                $photos[] = $entry;
            }
        }

        return ['photos' => $photos, 'plans' => $plans];
    }

    private function cover(int $immobileId): string
    {
        if ($immobileId <= 0) {
            return '';
        }

        $row = ImmobileImmagine::find([
            'immobile_id' => $immobileId,
            'planimetria' => 'false',
        ], 1, 'position', 'ASC');

        if (!is_array($row)) {
            return '';
        }

        return $this->imageEntry($row)['thumb'];
    }

    /**
     * Costruisce gli URL di visualizzazione di un'immagine.
     *
     * Se l'immagine è stata processata (`resized`), usa le varianti responsive
     * webp generate dall'ImageProcessor; altrimenti ricade sulla `source_url`
     * remota (così l'immagine è visibile anche prima del resize).
     *
     * @param array<string, mixed> $row
     * @return array{url:string, thumb:string, srcset:string, titolo:string, planimetria:bool}
     */
    private function imageEntry(array $row): array
    {
        $titolo = (string) ($row['titolo'] ?? '');
        $planimetria = immobiliIsTrue($row['planimetria'] ?? '');
        $source = trim((string) ($row['source_url'] ?? ''));
        $file = trim((string) ($row['file'] ?? ''));
        $upload = trim((string) ($row['upload'] ?? ''));

        $path = $GLOBALS['PATH'] ?? null;
        $uploadBase = is_object($path) ? rtrim((string) ($path->upload ?? ''), '/') : '';

        // Immagine caricata a mano: le varianti sono generate dal framework nel
        // folder del model (immobili/).
        if ($upload !== '' && $uploadBase !== '') {
            $name = pathinfo($upload, PATHINFO_FILENAME);
            $entry = $this->variants($uploadBase.'/immobili/'.$name, $titolo, $planimetria);
            // `src` = originale con estensione, consumabile da Image::src()/__swiper.
            $entry['src'] = $uploadBase.'/immobili/'.$upload;
            $entry['processed'] = true;
            return $entry;
        }

        // Immagine da feed già processata: varianti generate dall'ImageProcessor.
        if (immobiliIsTrue($row['resized'] ?? '') && $file !== '' && $uploadBase !== '') {
            $dir = trim(dirname($file), '/.');
            $name = pathinfo($file, PATHINFO_FILENAME);
            $entry = $this->variants($uploadBase.'/'.($dir !== '' ? $dir.'/' : '').$name, $titolo, $planimetria);
            $entry['src'] = $uploadBase.'/'.ltrim($file, '/');
            $entry['processed'] = true;
            return $entry;
        }

        // Non ancora processata: source remota (non compatibile con __swiper).
        return [
            'url'         => $source,
            'thumb'       => $source,
            'srcset'      => '',
            'titolo'      => $titolo,
            'planimetria' => $planimetria,
            'src'         => $source,
            'processed'   => false,
        ];
    }

    /**
     * Costruisce url/thumb/srcset dalle varianti responsive webp per un dato base.
     *
     * @return array{url:string, thumb:string, srcset:string, titolo:string, planimetria:bool}
     */
    private function variants(string $base, string $titolo, bool $planimetria): array
    {
        $sizes = defined('RESPONSIVE_IMAGE_SIZES') ? RESPONSIVE_IMAGE_SIZES : [480, 960, 1440];
        $srcset = [];
        foreach ($sizes as $size) {
            $srcset[] = $base.'-'.$size.'.webp '.((int) $size).'w';
        }

        return [
            'url'         => $base.'-1200.webp',
            'thumb'       => $base.'-620.webp',
            'srcset'      => implode(', ', $srcset),
            'titolo'      => $titolo,
            'planimetria' => $planimetria,
        ];
    }

    /**
     * @return array{titolo: string, testo: string, testo_breve: string}
     */
    private function descrizione(int $immobileId, ?string $locale): array
    {
        $empty = ['titolo' => '', 'testo' => '', 'testo_breve' => ''];

        if ($immobileId <= 0) {
            return $empty;
        }

        $locale = strtolower(trim((string) ($locale ?? (function_exists('__l') ? __l() : 'it')))) ?: 'it';

        $row = ImmobileDescrizione::find(['immobile_id' => $immobileId, 'lingua' => $locale], 1);

        if (!is_array($row) || !isset($row['id'])) {
            // Fallback: italiano, poi qualunque lingua disponibile.
            $row = ImmobileDescrizione::find(['immobile_id' => $immobileId, 'lingua' => 'it'], 1);

            if (!is_array($row) || !isset($row['id'])) {
                $row = ImmobileDescrizione::find(['immobile_id' => $immobileId], 1);
            }
        }

        if (!is_array($row)) {
            return $empty;
        }

        return [
            'titolo'      => (string) ($row['titolo'] ?? ''),
            'testo'       => (string) ($row['testo'] ?? ''),
            'testo_breve' => (string) ($row['testo_breve'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function geoJson(array $row, array $data): array
    {
        $lat = (float) ($row['latitudine'] ?? 0);
        $lng = (float) ($row['longitudine'] ?? 0);

        if ($lat === 0.0 || $lng === 0.0) {
            return [];
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
            'properties' => [
                'id'      => $data['id'],
                'name'    => $data['prettyName'],
                'price'   => $data['prezzo'],
                'surface' => $data['superficie'],
                'url'     => $data['url'],
                'cover'   => $data['cover'] ?? '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function gmaps(array $row): string
    {
        $lat = trim((string) ($row['latitudine'] ?? ''));
        $lng = trim((string) ($row['longitudine'] ?? ''));

        if ($lat === '' || $lng === '') {
            return '';
        }

        return 'https://www.google.com/maps?q='.$lat.','.$lng.'&z='.((int) ($row['zoom'] ?? 15) ?: 15).'&output=embed';
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
}
