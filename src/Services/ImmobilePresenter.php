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

        $media = $this->images($id);
        $data['images'] = $media['photos'];
        $data['imagesAlt'] = $media['photosAlt'];
        $data['image'] = $media['photos'][0] ?? '';
        $data['planimetrie'] = $media['plans'];
        $data['planimetrieAlt'] = $media['plansAlt'];
        $data['cover'] = $media['cover'];

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

        $tipologia = Taxonomy::tipologiaNome($provider, (string) ($row['tipologia_id'] ?? ''));
        if ($tipologia === '') {
            $tipologia = (string) ($attributi['tipologia'] ?? '');
        }

        $comune = $this->comuneName($row, $attributi);

        // Fonte unica del prezzo, condivisa con la lista backend.
        $prezzo = self::price($row);

        return array_merge($row, [
            'id'            => (int) ($row['id'] ?? 0),
            'nome'          => (string) ($row['nome'] ?? ''),
            'tipologia'     => $tipologia,
            'comune'        => $comune,
            'contratto'     => $affitto ? 'Affitto' : 'Vendita',
            'prettyPrezzo'  => $prezzo,
            'spese_mensili' => $row['spese_mensili'],
            'prettySpeseMensili' => empty($row['spese_mensili']) ? '':  number_format($row['spese_mensili'], 0, '', '.').'€/Mese',
            'prettySuperficie'    => self::formatSurface($row['superficie'] ?? 0),
            'locali'        => (int) ($row['n_locali'] ?? 0),
            'camere'        => (int) ($row['n_camere'] ?? 0),
            'bagni'         => (int) ($row['n_bagni'] ?? 0),
            'classe'        => strtoupper((string) ($row['classe_energetica'] ?? '')),
            'evidence'      => immobiliIsTrue($row['evidence'] ?? ''),
            'sold'          => immobiliIsTrue($row['sold'] ?? ''),
            'latitudine'    => (string) ($row['latitudine'] ?? ''),
            'longitudine'   => (string) ($row['longitudine'] ?? ''),
            'prettyName'    => $this->prettyName($tipologia, $comune, (string) ($row['strada'] ?? '')),
            'prettyAddress' => $this->prettyAddress($row, $comune),
            'attributi'     => $attributi,
        ]);

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
     * nome comune e tipologia risolti (con fallback JSON Gestim). Unica fonte
     * del calcolo, condivisa da sync e backfill.
     *
     * @param array<string, mixed> $row  riga immobile (o campi normalizzati) con
     *   almeno: provider, tipologia_id, comune_id, attributi
     * @return array{comune_nome: string, tipologia_nome: string}
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

        return [
            'comune_nome'    => $comune,
            'tipologia_nome' => $tipologia,
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
     * URL della foto copertina (thumb della prima foto) per la lista backend.
     * Delega al resolver `cover()` già usato da card(): '' se assente.
     *
     * @param array<string, mixed> $row
     */
    public function coverImage(array $row): string
    {
        return $this->cover((int) ($row['id'] ?? 0));
    }

    /**
     * Slug URL-friendly da un testo (translitterazione ASCII deterministica,
     * indipendente dal locale). '' se il testo è vuoto.
     */
    public static function slug(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Translitterazione deterministica dei diacritici più comuni.
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
        ];

        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, $map);

        // Diacritici residui: tentativo con iconv, poi rimozione.
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }

        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }

    /**
     * Superficie formattata in metri quadri (es. "120 mq"). '' se non positiva.
     */
    public static function formatSurface(mixed $value, string $unit = 'mq'): string
    {
        $surface = (int) round((float) $value);

        if ($surface <= 0) {
            return '';
        }

        return number_format($surface, 0, ',', '.').' '.$unit;
    }

    /**
     * Prezzo formattato per lista backend e scheda (fonte unica del prezzo).
     * Il prezzo è sempre in `prezzo`; per l'affitto (`contratto_id` = 'A') si
     * aggiunge ' /mese'. "Trattativa riservata" se il flag riservato è attivo;
     * "—" se il prezzo manca. La formattazione euro è delegata a
     * `immobiliFormatPrice` — la stessa usata ovunque nel modulo.
     *
     * @param array<string, mixed> $row
     */
    public static function price(array $row): string
    {
        if (immobiliIsTrue($row['trattativa_riservata'] ?? '')) {
            return 'Trattativa riservata';
        }

        $price = immobiliFormatPrice($row['prezzo'] ?? 0);

        if ($price === '') {
            return '—';
        }

        $isRent = strtoupper(trim((string) ($row['contratto_id'] ?? ''))) === 'A';

        return $isRent ? $price.' /mese' : $price;
    }

    /**
     * Nome (riferimento) + sottotitolo tipologia/indirizzo per la lista backend.
     * Possiede la cella: restituisce HTML già escaped.
     *
     * @param array<string, mixed> $row
     */
    public static function nome(array $row): string
    {
        $nome = htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES);

        $tipologia = trim((string) ($row['tipologia_nome'] ?? ''));
        $strada    = ucwords(strtolower(trim((string) ($row['strada'] ?? ''))));
        $indirizzo = trim((string) ($row['indirizzo'] ?? ''));
        $comune    = trim((string) ($row['comune_nome'] ?? ''));

        $via = trim($strada.' '.$indirizzo);
        $loc = trim($via.($comune !== '' ? ', '.$comune : ''), ', ');
        $sub = $tipologia;

        if ($loc !== '') {
            $sub = $sub !== '' ? $sub.' | '.$loc : $loc;
        }

        $sub = htmlspecialchars($sub, ENT_QUOTES);

        return $nome.($sub !== '' ? "<span class=\"d-block text-muted small\">{$sub}</span>" : '');
    }

    /**
     * Restituisce collezioni semplici, adatte alle view e ai media builder:
     * le liste mantengono l'ordinamento del DB, mentre le mappe associano a
     * ogni sorgente il relativo titolo (usato come alt/caption).
     *
     * @return array{
     *   photos: array<int, string>,
     *   photosAlt: array<string, string>,
     *   plans: array<int, string>,
     *   plansAlt: array<string, string>,
     *   cover: string
     * }
     */
    private function images(int $immobileId): array
    {
        $photos = [];
        $photosAlt = [];
        $plans = [];
        $plansAlt = [];
        $cover = '';

        if ($immobileId <= 0) {
            return [
                'photos' => $photos,
                'photosAlt' => $photosAlt,
                'plans' => $plans,
                'plansAlt' => $plansAlt,
                'cover' => $cover,
            ];
        }

        $rows = ImmobileImmagine::find(['immobile_id' => $immobileId], null, 'position', 'ASC');

        foreach ($this->rows($rows) as $image) {
            $entry = $this->imageEntry($image);

            $src = (string) ($entry['src'] ?? '');

            if ($src === '') {
                continue;
            }

            $alt = trim((string) ($entry['titolo'] ?? ''));

            if ($entry['planimetria']) {
                $plans[] = $src;
                $plansAlt[$src] = $alt;
            } else {
                $photos[] = $src;
                $photosAlt[$src] = $alt;

                if ($cover === '') {
                    $cover = (string) ($entry['thumb'] ?? ($entry['url'] ?? $src));
                }
            }
        }

        return [
            'photos' => $photos,
            'photosAlt' => $photosAlt,
            'plans' => $plans,
            'plansAlt' => $plansAlt,
            'cover' => $cover,
        ];
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
