<?php

namespace Wonder\Plugin\Immobili\Catalog;

use Wonder\App\Support\MediaFileManager;
use Wonder\Plugin\Immobili\Media\MediaUrl;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Models\Taxonomy\Comune;
use Wonder\Plugin\Immobili\Models\Taxonomy\Provincia;
use Wonder\Plugin\Immobili\Support\EnergyScale;
use Wonder\Plugin\Immobili\Support\Taxonomy;
use Wonder\Support\Prettify\Address;

/**
 * View-model della residenza: cover (prima immagine), URL/anteprime immagini,
 * etichetta timeline e stato derivato. Le immagini vivono nella colonna JSON
 * `images` della residenza (array di filename), non più in una tabella figlia.
 * Le classi utility del frontend restano nelle view; qui vivono solo i dati.
 */
final class ResidenzaPresenter
{
    /**
     * Presentazione completa per il dettaglio, come ImmobilePresenter::present().
     *
     * @param array<string, mixed> $row
     */
    public function present(array $row): object
    {
        $data = $row;
        $data['nome'] = (string) ($row['nome'] ?? '');
        $data['sito_url'] = (string) ($row['sito_url'] ?? '');
        $data['descrizione_breve'] = (string) ($row['descrizione_breve'] ?? $data['nome']);
        $data['descrizione_lunga'] = (string) ($row['descrizione_lunga'] ?? '');
        $data['unita_abitative'] = (int) ($row['unita_abitative'] ?? 0);
        $data['unita_commerciali'] = (int) ($row['unita_commerciali'] ?? 0);
        $data['box'] = (int) ($row['box'] ?? 0);
        $data['features'] = is_array($row['features'] ?? null) ? $row['features'] : [];
        $data['prettyAddress'] = $this->prettyAddress($row);
        $data['url'] = __r('residenze.detail', ['slug' => (string) ($row['slug'] ?? '')]);

        $images = $this->images($row);
        $data['images'] = array_column($images, 'src');
        $data['imagesAlt'] = array_column($images, 'alt', 'src');
        $data['image'] = $data['images'][0] ?? '';
        $data['cover'] = $this->cover($row);
        $data['logoUrl'] = self::imageUrl(self::firstFile($row['logo'] ?? ''));
        $data['capitolatoUrl'] = self::imageUrl(self::firstFile($row['capitolato'] ?? ''));

        $data['inizio'] = self::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0));
        $data['fine'] = self::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0));
        $data['stato'] = self::stato($row);
        $data['energyScale'] = EnergyScale::make((string) ($row['classe_energetica'] ?? ''), '', '');
        $data['geo_json'] = $this->geoJson($row);

        return (object) $data;
    }

    /** @param array<string, mixed> $row */
    public function prettyAddress(array $row): string
    {
        $street = trim((string) ($row['indirizzo'] ?? ''));
        $number = trim((string) ($row['civico'] ?? ''));
        $comuneRow = Taxonomy::byId(Comune::class, (int) ($row['comune_id'] ?? 0));
        $comune = trim((string) ($comuneRow['nome'] ?? ''));
        if ($comune === '') {
            $comune = trim((string) ($row['comune_nome'] ?? ''));
        }
        $provincia = Taxonomy::byId(Provincia::class, (int) ($comuneRow['provincia_id'] ?? 0));
        $address = Address::prettify(
            $street,
            $number,
            trim((string) ($row['cap'] ?? '')),
            $comune,
            (string) ($provincia['sigla'] ?? ''),
            ''
        );

        if ($address->line !== '--') {
            return $address->line;
        }

        // Come per gli immobili, conserva i dati disponibili se incompleti.
        $parts = [];
        if ($street !== '') {
            $parts[] = $number !== '' ? $street.', '.$number : $street;
        }
        if ($comune !== '') {
            $parts[] = $comune;
        }

        return implode(' — ', $parts);
    }

    /** Etichetta timeline: "" se anno assente, "2025" o "03/2025". */
    public static function timelineLabel(?int $anno, ?int $mese): string
    {
        $anno = (int) $anno;

        if ($anno <= 0) {
            return '';
        }

        $mese = (int) $mese;

        if ($mese >= 1 && $mese <= 12) {
            return sprintf('%02d/%d', $mese, $anno);
        }

        return (string) $anno;
    }

    /**
     * Stato della residenza: venduto | in_arrivo | in_corso | completato.
     *
     * @param array<string, mixed> $row
     */
    public static function stato(array $row, ?int $todayYear = null, ?int $todayMonth = null): string
    {
        if (self::isTrue($row['sold'] ?? '')) {
            return 'venduto';
        }

        $override = strtolower(trim((string) ($row['stato'] ?? '')));

        if (in_array($override, ['in_arrivo', 'in_corso', 'completato'], true)) {
            return $override;
        }

        $todayYear ??= (int) date('Y');
        $todayMonth ??= (int) date('n');
        $today = $todayYear * 100 + $todayMonth;

        $start = self::yearMonth($row['inizio_anno'] ?? null, $row['inizio_mese'] ?? null, 1);
        $end = self::yearMonth($row['fine_anno'] ?? null, $row['fine_mese'] ?? null, 12);

        if ($start !== null && $today < $start) {
            return 'in_arrivo';
        }

        if ($end !== null && $today > $end) {
            return 'completato';
        }

        return 'in_corso';
    }

    /** URL upload assoluto di un filename della cartella residenze. */
    public static function imageUrl(string $file): string
    {
        return MediaUrl::url($file, Residenza::$folder);
    }

    /** URL della variante webp responsive -620 di un filename; '' se vuoto. */
    public static function previewUrl(string $file): string
    {
        return MediaUrl::preview($file, Residenza::$folder);
    }

    /**
     * Primo filename di una colonna file/immagine (JSON array, array già
     * decodificato o formato legacy stringa). '' se assente.
     */
    public static function firstFile(mixed $stored): string
    {
        return MediaUrl::firstFile($stored);
    }

    /**
     * Cover = anteprima della prima immagine della gallery. '' se vuota.
     *
     * @param array<string, mixed> $row
     */
    public function cover(array $row): string
    {
        foreach ($this->files($row) as $file) {
            $url = self::previewUrl($file);

            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Immagini della gallery (src assoluto + alt), lette dalla colonna JSON.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{src: string, alt: string}>
     */
    public function images(array $row): array
    {
        $alt = (string) ($row['nome'] ?? '');
        $images = [];

        foreach ($this->files($row) as $file) {
            $src = self::imageUrl($file);

            if ($src === '') {
                continue;
            }

            $images[] = ['src' => $src, 'alt' => $alt];
        }

        return $images;
    }

    /**
     * Anteprime della gallery (variante responsive), per le card di lista:
     * stessa forma di `images()`, ma con gli URL leggeri.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{src: string, alt: string}>
     */
    public function previews(array $row): array
    {
        $alt = (string) ($row['nome'] ?? '');
        $previews = [];

        foreach ($this->files($row) as $file) {
            $src = self::previewUrl($file);

            if ($src === '') {
                continue;
            }

            $previews[] = ['src' => $src, 'alt' => $alt];
        }

        return $previews;
    }

    /**
     * Feature GeoJSON (Point) della residenza, pronta per il componente
     * `map.php`. Fonte unica del `geo_json` delle residenze (usata sia dal
     * dettaglio sia dalla collezione di `ResidenzaQuery::geojson()`). '[]' se
     * mancano le coordinate. Mirror di `ImmobilePresenter::geoJson()`; le
     * residenze non hanno prezzo/superficie da mostrare sul marker.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function geoJson(array $row): array
    {
        $lat = (float) ($row['latitudine'] ?? 0);
        $lng = (float) ($row['longitudine'] ?? 0);

        if ($lat === 0.0 || $lng === 0.0) {
            return [];
        }

        $nome = trim((string) ($row['nome'] ?? ''));
        $name = $nome !== '' ? $nome : ($this->prettyAddress($row) ?: 'Residenza');

        $url = trim((string) ($row['url'] ?? ''));
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($url === '' && $slug !== '') {
            $url = __r('residenza.detail', ['slug' => $slug]);
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
            'properties' => [
                'id'           => (int) ($row['id'] ?? 0),
                'name'         => $name,
                'price'        => '',
                'surface'      => '',
                'url'          => $url,
                'cover'        => $this->cover($row),
                'category'     => '',
                'variant'      => $this->markerVariant($row),
                'variantLabel' => $this->markerVariantLabel($row),
            ],
        ];
    }

    /**
     * Variante visuale del marker (le CSS supportano default|featured|sold).
     *
     * @param array<string, mixed> $row
     */
    private function markerVariant(array $row): string
    {
        if (self::isTrue($row['sold'] ?? '')) {
            return 'sold';
        }

        if (self::isTrue($row['evidence'] ?? '')) {
            return 'featured';
        }

        return 'default';
    }

    /**
     * Etichetta di stato mostrata nella scheda del marker.
     *
     * @param array<string, mixed> $row
     */
    private function markerVariantLabel(array $row): string
    {
        return match (self::stato($row)) {
            'venduto'    => 'Venduto',
            'in_arrivo'  => 'In arrivo',
            'in_corso'   => 'In corso',
            'completato' => 'Completato',
            default      => '',
        };
    }

    /**
     * Filename della gallery decodificati dalla colonna JSON `images`.
     *
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    private function files(array $row): array
    {
        return MediaFileManager::decodeStoredFiles($row['images'] ?? []);
    }

    private static function yearMonth(mixed $anno, mixed $mese, int $defaultMonth): ?int
    {
        $anno = (int) $anno;

        if ($anno <= 0) {
            return null;
        }

        $mese = (int) $mese;

        if ($mese < 1 || $mese > 12) {
            $mese = $defaultMonth;
        }

        return $anno * 100 + $mese;
    }

    private static function isTrue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'si', 'sì', 'yes'], true);
    }
}
