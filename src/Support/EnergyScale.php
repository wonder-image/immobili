<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Support;

/**
 * Scala della classe energetica (APE) di un immobile: deriva l'elenco ordinato
 * delle classi in base alla legge di riferimento, evidenzia la classe corrente
 * e formatta il valore IPE. Unica fonte per la palette canonica verde→rosso,
 * condivisa da tutti i punti che disegnano la scala (frontend, badge, linea).
 *
 * Leggi:
 *  - '1' Legge 90/2013  → A4, A3, A2, A1, B, C, D, E, F, G
 *  - '0' DL 192/2005    → A+, A, B, C, D, E, F, G
 *
 * Quando la legge non è nota si deduce dalla lettera della classe.
 */
final class EnergyScale
{
    /** @var array<int, string> Legge 90/2013 (id '1'). */
    private const LAW_NEW = ['A4', 'A3', 'A2', 'A1', 'B', 'C', 'D', 'E', 'F', 'G'];

    /** @var array<int, string> DL 192/2005 (id '0'). */
    private const LAW_OLD = ['A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G'];

    /**
     * Colore di sfondo e colore testo per ogni classe (gradiente APE canonico).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PALETTE = [
        'A4' => ['#00a651', '#0b3d1c'],
        'A+' => ['#00a651', '#0b3d1c'],
        'A3' => ['#4cb847', '#0f3a0d'],
        'A'  => ['#4cb847', '#0f3a0d'],
        'A2' => ['#8dc63f', '#2f4708'],
        'A1' => ['#c3d82e', '#3f480a'],
        'B'  => ['#fff200', '#5c5600'],
        'C'  => ['#fdb913', '#5c400a'],
        'D'  => ['#f7941e', '#5e3506'],
        'E'  => ['#f26522', '#5e260a'],
        'F'  => ['#ed1c24', '#ffffff'],
        'G'  => ['#c1272d', '#ffffff'],
    ];

    private const FALLBACK_COLOR = ['#9ca3af', '#1f2937'];

    /** @param array<int, array{label: string, bg: string, text: string, width: int, current: bool}> $bands */
    private function __construct(
        private readonly string $classe,
        private readonly string $ipe,
        private readonly array $bands,
    ) {
    }

    /**
     * Costruisce la scala da un immobile presentato. Ritorna null quando la
     * classe energetica è assente: in quel caso il componente non si disegna.
     */
    public static function forImmobile(object $immobile): ?self
    {
        return self::make(
            (string) ($immobile->classe ?? $immobile->classe_energetica ?? ''),
            (string) ($immobile->ipe ?? ''),
            (string) ($immobile->legge_classe_energetica_id ?? ''),
        );
    }

    /**
     * Risolve la scala dagli argomenti di un componente: usa un'istanza già
     * pronta (`scale`) oppure la costruisce dall'immobile (`immobile`). Permette
     * di usare le tre tipologie (badge/line/scale) sia da sole sia composte.
     *
     * @param array<string, mixed> $args
     */
    public static function fromArgs(array $args): ?self
    {
        $scale = $args['scale'] ?? null;

        if ($scale instanceof self) {
            return $scale;
        }

        $immobile = $args['immobile'] ?? null;

        return is_object($immobile) ? self::forImmobile($immobile) : null;
    }

    public static function make(string $classe, string $ipe, string $leggeId): ?self
    {
        $classe = strtoupper(trim($classe));

        if ($classe === '') {
            return null;
        }

        $scale = self::scaleFor($classe, trim($leggeId));

        // Dati incoerenti (es. legge "nuova" ma classe "A+"): usa la scala che
        // contiene davvero la classe corrente, così resta sempre evidenziata.
        if (!in_array($classe, $scale, true)) {
            $other = $scale === self::LAW_NEW ? self::LAW_OLD : self::LAW_NEW;

            if (in_array($classe, $other, true)) {
                $scale = $other;
            }
        }

        $last = count($scale) - 1;
        $bands = [];

        foreach ($scale as $index => $label) {
            [$bg, $text] = self::PALETTE[$label] ?? self::FALLBACK_COLOR;

            $bands[] = [
                'label'   => $label,
                'bg'      => $bg,
                'text'    => $text,
                // Larghezza barra crescente 40%→96% dalla classe migliore alla peggiore.
                'width'   => $last > 0 ? (int) round(40 + ($index / $last) * 56) : 100,
                'current' => $label === $classe,
            ];
        }

        return new self($classe, self::formatIpe($ipe), $bands);
    }

    /** @return array<int, array{label: string, bg: string, text: string, width: int, current: bool}> */
    public function bands(): array
    {
        return $this->bands;
    }

    public function classe(): string
    {
        return $this->classe;
    }

    /** Colore di sfondo della classe corrente (per il badge). */
    public function currentBg(): string
    {
        foreach ($this->bands as $band) {
            if ($band['current']) {
                return $band['bg'];
            }
        }

        return self::FALLBACK_COLOR[0];
    }

    /** Colore testo della classe corrente (per il badge). */
    public function currentText(): string
    {
        foreach ($this->bands as $band) {
            if ($band['current']) {
                return $band['text'];
            }
        }

        return self::FALLBACK_COLOR[1];
    }

    /** IPE già formattato (stringa vuota quando non disponibile). */
    public function ipe(): string
    {
        return $this->ipe;
    }

    public function hasIpe(): bool
    {
        return $this->ipe !== '';
    }

    /** @return array<int, string> */
    private static function scaleFor(string $classe, string $leggeId): array
    {
        if ($leggeId === '1') {
            return self::LAW_NEW;
        }

        if ($leggeId === '0') {
            return self::LAW_OLD;
        }

        if (in_array($classe, ['A4', 'A3', 'A2', 'A1'], true)) {
            return self::LAW_NEW;
        }

        if ($classe === 'A+') {
            return self::LAW_OLD;
        }

        return self::LAW_NEW;
    }

    /**
     * Formatta l'IPE con separatore decimale italiano, senza zeri superflui.
     * Ritorna '' quando il valore è vuoto o non positivo.
     */
    private static function formatIpe(string $ipe): string
    {
        $ipe = trim($ipe);

        if ($ipe === '') {
            return '';
        }

        $value = (float) str_replace(',', '.', $ipe);

        if ($value <= 0.0) {
            return '';
        }

        $formatted = number_format($value, 2, ',', '.');
        $formatted = rtrim($formatted, '0');

        return rtrim($formatted, ',');
    }
}
