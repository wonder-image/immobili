<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf\Support;

/**
 * Colore RGB immutabile, con parsing di stringhe esadecimali (`#RRGGBB`,
 * `RRGGBB`, `#RGB`) e calcolo del colore di contrasto (`neutral()`) per il testo
 * leggibile sopra questo colore. Nessuna dipendenza da FPDF: pura utility.
 */
final class Color
{
    public function __construct(
        public readonly int $r,
        public readonly int $g,
        public readonly int $b,
    ) {
    }

    /**
     * Parsa una stringa esadecimale. In caso di formato non valido usa
     * `$fallback` ([r, g, b]).
     *
     * @param array{0:int,1:int,2:int} $fallback
     */
    public static function fromHex(string $hex, array $fallback = [0, 0, 0]): self
    {
        $hex = ltrim(strtolower(trim($hex)), '#');

        if (strlen($hex) === 3 && ctype_xdigit($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) === 6 && ctype_xdigit($hex)) {
            return new self(
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            );
        }

        return new self(
            self::clamp($fallback[0] ?? 0),
            self::clamp($fallback[1] ?? 0),
            self::clamp($fallback[2] ?? 0),
        );
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    public function rgb(): array
    {
        return [$this->r, $this->g, $this->b];
    }

    /**
     * Colore di contrasto per il testo sopra questo colore: bianco su sfondi
     * scuri, nero su sfondi chiari (luminanza percettiva).
     */
    public function neutral(): self
    {
        $luminance = (0.299 * $this->r) + (0.587 * $this->g) + (0.114 * $this->b);

        return $luminance > 128
            ? new self(0, 0, 0)
            : new self(255, 255, 255);
    }

    private static function clamp(int $value): int
    {
        return max(0, min(255, $value));
    }
}
