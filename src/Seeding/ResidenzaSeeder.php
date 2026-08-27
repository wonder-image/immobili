<?php

namespace Wonder\Plugin\Immobili\Seeding;

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Support\Forms\ResidenzaForm;
use Wonder\Plugin\Immobili\Support\Slug;

/**
 * Seed di residenze di esempio per la verifica locale (frontend + backend).
 * Le immagini sono placeholder generati con GD e salvati come file reali nella
 * cartella upload (`immobili/residenze/`), con le varianti responsive webp: la
 * gallery si comporta come un upload vero. I file/record di seed sono
 * riconoscibili dal prefisso `seed-` (file) e `seedres-` (code) e vengono
 * rigenerati a ogni run; gli immobili collegati sono agganciati via
 * `immobili.residenza_id`.
 */
final class ResidenzaSeeder
{
    private const CODE_PREFIX = 'seedres-';
    private const FILE_PREFIX = 'seed-';
    private const FOLDER = 'residenze';

    /** Città con coordinate (comune_nome + mappa); comune_id risolto se in tassonomia. */
    private const CITIES = [
        'Milano'  => [45.4642, 9.1900],
        'Bergamo' => [45.6983, 9.6773],
        'Torino'  => [45.0703, 7.6869],
        'Firenze' => [43.7696, 11.2558],
        'Napoli'  => [40.8518, 14.2681],
    ];

    /** Palette di sfondo dei placeholder (una tinta per residenza). */
    private const COLORS = [
        [37, 99, 148],   // blu
        [39, 122, 96],   // verde
        [148, 96, 37],   // ocra
        [110, 72, 140],  // viola
        [176, 68, 84],   // rosso
    ];

    /**
     * Set curato: stati misti (completato / in corso / in arrivo) rispetto a
     * oggi, features dal catalogo, unità e classe energetica varie.
     *
     * @var array<int, array<string, mixed>>
     */
    private const DEMO = [
        [
            'nome' => 'Borgo delle Rose', 'city' => 'Milano',
            'inizio' => [2021, 3], 'fine' => [2023, 6],
            'unita' => 24, 'classe' => 'A', 'sito' => true,
            'features' => ['giardino', 'ascensore', 'box_auto', 'area_verde'],
            'breve' => 'Residenza completata immersa nel verde, a pochi minuti dal centro.',
        ],
        [
            'nome' => 'Le Terrazze sul Parco', 'city' => 'Bergamo',
            'inizio' => [2024, 9], 'fine' => [2026, 12],
            'unita' => 18, 'classe' => 'A', 'sito' => false,
            'features' => ['terrazzo', 'ascensore', 'fotovoltaico', 'videosorveglianza'],
            'breve' => 'Appartamenti con ampie terrazze affacciate sul parco cittadino.',
        ],
        [
            'nome' => 'Residenza Aurora', 'city' => 'Torino',
            'inizio' => [2027, 1], 'fine' => [2029, 6],
            'unita' => 32, 'classe' => 'A', 'sito' => true,
            'features' => ['domotica', 'climatizzazione', 'box_auto', 'cantina'],
            'breve' => 'Nuovo complesso domotico in arrivo, altamente efficiente.',
        ],
        [
            'nome' => 'Corte Verde', 'city' => 'Firenze',
            'inizio' => [2025, 4], 'fine' => [2027, 3],
            'unita' => 12, 'classe' => 'B', 'sito' => false,
            'features' => ['giardino', 'fotovoltaico', 'area_verde'],
            'breve' => 'Piccola corte residenziale a basso impatto, cantiere in corso.',
        ],
        [
            'nome' => 'Palazzo Novecento', 'city' => 'Napoli',
            'inizio' => [2019, 5], 'fine' => [2021, 11],
            'unita' => 8, 'classe' => 'B', 'sito' => true,
            'features' => ['ascensore', 'terrazzo', 'cantina'],
            'breve' => 'Ristrutturazione d\'epoca completata nel cuore della città.',
        ],
    ];

    private const LONG = 'Progetto di esempio generato dal seeder per verificare il reparto '
        .'Residenze: timeline, gallery, features, classe energetica, capitolato, '
        .'mappa e immobili collegati. I contenuti sono fittizi.';

    /**
     * Rigenera il set di residenze di esempio. Ritorna il numero di residenze
     * create.
     */
    public function seed(): int
    {
        $this->clear();

        $dir = $this->uploadDir();
        $nameToId = $this->municipalityIndex();
        $immobili = $this->linkableImmobili();
        $perResidenza = max(2, (int) floor(count($immobili) / max(1, count(self::DEMO))));

        $created = 0;
        $offset = 0;

        foreach (self::DEMO as $i => $demo) {
            $city = (string) $demo['city'];
            [$lat, $lng] = self::CITIES[$city] ?? [45.4642, 9.1900];
            $num = $i + 1;
            $color = self::COLORS[$i % count(self::COLORS)];

            $data = [
                'code'              => self::CODE_PREFIX.$num,
                'nome'              => (string) $demo['nome'],
                'slug'              => Slug::fromParts([(string) $demo['nome']], Residenza::class, null, 'residenza'),
                'logo'              => $this->logo($dir, $num, (string) $demo['nome'], $color),
                'images'            => $this->gallery($dir, $num, (string) $demo['nome'], $color),
                'sito_url'          => $demo['sito'] ? 'https://www.example.com/' : '',
                'inizio_anno'       => (string) $demo['inizio'][0],
                'inizio_mese'       => (string) $demo['inizio'][1],
                'fine_anno'         => (string) $demo['fine'][0],
                'fine_mese'         => (string) $demo['fine'][1],
                'descrizione_breve' => (string) $demo['breve'],
                'descrizione_lunga' => self::LONG,
                'indirizzo'         => 'Via Esempio '.$num,
                'civico'            => (string) (($num * 7) % 90 + 1),
                'cap'               => str_pad((string) (10000 + $num * 137), 5, '0', STR_PAD_LEFT),
                'comune_id'         => (string) ($nameToId[$city] ?? ''),
                'comune_nome'       => $city,
                'latitudine'        => (string) $lat,
                'longitudine'       => (string) $lng,
                'zoom'              => '14',
                'classe_energetica' => (string) $demo['classe'],
                'unita_abitative'   => (string) $demo['unita'],
                'features'          => array_values((array) $demo['features']),
                'capitolato'        => '',
                'sold'              => 'false',
                'stato'             => '',
                'visible'           => 'true',
                'evidence'          => $num % 3 === 0 ? 'true' : 'false',
                'position'          => (string) $num,
            ];

            $result = Residenza::create($data);
            $residenzaId = (int) ($result->insert_id ?? 0);

            if ($residenzaId <= 0) {
                continue;
            }

            $slice = array_slice($immobili, $offset, $perResidenza);
            $offset += $perResidenza;

            foreach ($slice as $immobileId) {
                Immobile::update(['residenza_id' => $residenzaId], $immobileId);
            }

            $created++;
        }

        return $created;
    }

    /**
     * Rimuove le residenze di seed (code `seedres-*`), sgancia i loro immobili
     * e cancella i file immagine generati (`seed-*`).
     */
    public function clear(): void
    {
        foreach ($this->rows(Residenza::find([])) as $row) {
            $code = (string) ($row['code'] ?? '');
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0 || !str_starts_with($code, self::CODE_PREFIX)) {
                continue;
            }

            foreach ($this->rows(Immobile::find(['residenza_id' => $id])) as $immobile) {
                $immobileId = (int) ($immobile['id'] ?? 0);

                if ($immobileId > 0) {
                    Immobile::update(['residenza_id' => ''], $immobileId);
                }
            }

            Residenza::delete($id);
        }

        $dir = $this->uploadDir();

        foreach ((array) glob($dir.self::FILE_PREFIX.'*') as $file) {
            if (is_string($file) && is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Genera la gallery (3-4 immagini reali + varianti responsive) e ritorna i
     * filename da salvare nella colonna JSON `images`.
     *
     * @param array{0:int,1:int,2:int} $color
     * @return array<int, string>
     */
    private function gallery(string $dir, int $num, string $nome, array $color): array
    {
        $count = 3 + ($num % 2);
        $files = [];

        for ($p = 1; $p <= $count; $p++) {
            $file = self::FILE_PREFIX.'res'.$num.'-'.$p.'.jpg';
            $shade = $this->shade($color, $p);

            if ($this->makeImage($dir.$file, 2400, 1600, $shade, $nome, 'Foto '.$p, 'jpg')) {
                if (function_exists('imageResize')) {
                    imageResize($dir.$file);
                }
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Genera il logo (immagine quadrata) e ritorna il filename in un array
     * (formato colonna file).
     *
     * @param array{0:int,1:int,2:int} $color
     * @return array<int, string>
     */
    private function logo(string $dir, int $num, string $nome, array $color): array
    {
        $file = self::FILE_PREFIX.'reslogo'.$num.'.png';

        return $this->makeImage($dir.$file, 320, 320, $color, $nome, 'LOGO', 'png')
            ? [$file]
            : [];
    }

    /**
     * Crea un'immagine placeholder colorata con etichetta. Ritorna true se il
     * file è stato scritto. Richiede l'estensione GD.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    private function makeImage(string $path, int $w, int $h, array $rgb, string $title, string $sub, string $ext): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        $band = imagecolorallocatealpha($img, 0, 0, 0, 80);
        $white = imagecolorallocate($img, 245, 245, 245);

        imagefilledrectangle($img, 0, 0, $w, $h, $bg);
        imagefilledrectangle($img, 0, (int) ($h * 0.68), $w, $h, $band);

        // Titolo (nome residenza) ingrandito, sottotitolo più piccolo. La scala
        // è proporzionale alla larghezza così il testo resta leggibile sia sulle
        // foto (2400px) sia sul logo (320px).
        $titleScale = max(3, (int) round($w / 340));
        $this->text($img, $title, (int) ($w * 0.05), (int) ($h * 0.72), 5, $titleScale, $white);
        $this->text($img, strtoupper($sub), (int) ($w * 0.05), (int) ($h * 0.86), 5, max(2, $titleScale - 1), $white);

        $ok = $ext === 'png'
            ? (function_exists('imagepng') && imagepng($img, $path))
            : (function_exists('imagejpeg') && imagejpeg($img, $path, 90));

        imagedestroy($img);

        return (bool) $ok;
    }

    /**
     * Disegna testo con font GD integrato, ingrandito con un fattore di scala
     * (copyresampled) per renderlo leggibile su immagini grandi.
     */
    private function text(\GdImage $img, string $text, int $x, int $y, int $font, int $scale, int $color): void
    {
        $text = trim($text);

        if ($text === '') {
            return;
        }

        $w = imagefontwidth($font) * strlen($text);
        $h = imagefontheight($font);
        $buffer = imagecreatetruecolor(max(1, $w), max(1, $h));
        $bg = imagecolorallocate($buffer, 0, 0, 0);
        imagecolortransparent($buffer, $bg);
        imagefilledrectangle($buffer, 0, 0, $w, $h, $bg);
        imagestring($buffer, $font, 0, 0, $text, $color);

        imagecopyresized($img, $buffer, $x, $y, 0, 0, $w * $scale, $h * $scale, $w, $h);
        imagedestroy($buffer);
    }

    /**
     * Variazione di tinta per differenziare gli scatti della stessa residenza.
     *
     * @param array{0:int,1:int,2:int} $color
     * @return array{0:int,1:int,2:int}
     */
    private function shade(array $color, int $step): array
    {
        $delta = ($step - 1) * 22;

        return [
            max(0, min(255, $color[0] + $delta)),
            max(0, min(255, $color[1] + $delta)),
            max(0, min(255, $color[2] + $delta)),
        ];
    }

    /** Percorso filesystem (con slash finale) della cartella upload residenze. */
    private function uploadDir(): string
    {
        $base = rtrim((string) ($GLOBALS['PATH']->rUpload ?? ''), '/');

        $dir = $base.'/'.self::FOLDER.'/';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * Mappa nome comune → id dalla tassonomia (per risolvere la FK comune_id).
     *
     * @return array<string, string>
     */
    private function municipalityIndex(): array
    {
        $index = [];

        foreach (ResidenzaForm::municipalities() as $id => $name) {
            $id = trim((string) $id);
            $name = trim((string) $name);

            if ($id !== '' && $name !== '' && !isset($index[$name])) {
                $index[$name] = $id;
            }
        }

        return $index;
    }

    /**
     * Id degli immobili visibili da collegare (max 15, per non svuotare troppo
     * il catalogo).
     *
     * @return array<int, int>
     */
    private function linkableImmobili(): array
    {
        $ids = [];

        foreach ($this->rows(Immobile::find(['visible' => 'true', 'deleted' => 'false'], 15, 'creation', 'DESC')) as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Normalizza il risultato di Model::find (riga singola, lista o null) in
     * una lista di righe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (is_array($rows) && isset($rows['id'])) {
            return [$rows];
        }

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }
}
