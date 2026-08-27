<?php

namespace Wonder\Plugin\Immobili\Catalog;

/**
 * Forma comune delle card di lista dei due reparti.
 *
 * Esiste perché immobili e residenze hanno lo stesso guscio visivo ma partono
 * da dati diversi: qui le differenze si appiattiscono in slot opzionali, così
 * `view/components/card.php` non deve sapere cosa sta rendendo. I campi non
 * pertinenti a un reparto sono stringa vuota, mai assenti.
 *
 * NON sostituisce `ImmobilePresenter::card()`: quegli oggetti restano com'erano
 * perché li serializza anche l'API JSON della lista (`http/api/frontend/search.php`).
 */
final class CardViewModel
{
    /**
     * Varianti di resa disponibili per `view/components/card.php`. Cambiano solo
     * il markup: leggono tutte gli stessi campi di questo view-model.
     *
     * @var array<int, string>
     */
    public const VARIANTS = ['base', 'overlay', 'overlay-rich'];

    /**
     * @param object|null        $badge  (object) ['label' => string, 'variant' => string]
     * @param array<int, object> $meta   (object) ['icon' => string, 'text' => string]
     * @param array<int, string> $images URL delle anteprime, cover inclusa, per
     *                                   la gallery sfogliabile dentro la card
     */
    private function __construct(
        public readonly string $url,
        public readonly string $cover,
        public readonly ?object $badge,
        public readonly string $eyebrow,
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $highlight,
        public readonly string $excerpt,
        public readonly array $meta,
        public readonly array $images = [],
    ) {
    }

    /**
     * @param object $immobile oggetto prodotto da ImmobilePresenter::card()
     *
     * La gallery in-card legge `$immobile->images`, che `card()` NON popola:
     * le foto dell'immobile vivono in tabella figlia e caricarle qui
     * significherebbe una query per riga di lista. Chi vuole la gallery le
     * fornisce a monte, con una sola query per l'intera pagina; senza,
     * `images` resta la sola cover e la variante mostra un'immagine ferma.
     */
    public static function fromImmobile(object $immobile): self
    {
        $badge = null;

        if (!empty($immobile->sold)) {
            $badge = (object) [
                'label'   => (string) __t('components.immobili.card.sold'),
                'variant' => 'text-bg-danger',
            ];
        } elseif (!empty($immobile->evidence)) {
            $badge = (object) [
                'label'   => (string) __t('components.immobili.card.featured'),
                'variant' => 'text-bg-dark',
            ];
        }

        $tipologia = trim((string) ($immobile->tipologia ?? ''));
        $contratto = trim((string) ($immobile->contratto ?? ''));
        $eyebrow = $tipologia !== ''
            ? trim($tipologia.($contratto !== '' ? ' · '.$contratto : ''))
            : '';

        $meta = [];

        if (trim((string) ($immobile->prettySuperficie ?? '')) !== '') {
            $meta[] = (object) ['icon' => 'bi bi-rulers', 'text' => (string) $immobile->prettySuperficie];
        }

        if ((int) ($immobile->locali ?? 0) > 0) {
            $meta[] = (object) [
                'icon' => 'bi bi-door-open',
                'text' => (int) $immobile->locali.' '.__t('components.immobili.card.rooms'),
            ];
        }

        if ((int) ($immobile->camere ?? 0) > 0) {
            $meta[] = (object) ['icon' => 'bi bi-house', 'text' => (string) (int) $immobile->camere];
        }

        if ((int) ($immobile->bagni ?? 0) > 0) {
            $meta[] = (object) ['icon' => 'bi bi-droplet', 'text' => (string) (int) $immobile->bagni];
        }

        $cover = (string) ($immobile->cover ?? '');
        $images = array_values(array_filter(
            array_map(
                static fn ($i): string => is_string($i) ? trim($i) : (string) ($i->src ?? ''),
                is_array($immobile->images ?? null) ? $immobile->images : []
            ),
            static fn (string $src): bool => $src !== ''
        ));

        if ($images === [] && $cover !== '') {
            $images = [$cover];
        }

        return new self(
            url:       (string) ($immobile->url ?? '#'),
            cover:     $cover,
            badge:     $badge,
            eyebrow:   $eyebrow,
            title:     (string) ($immobile->prettyName ?? ''),
            subtitle:  (string) ($immobile->prettyAddress ?? ''),
            highlight: trim((string) ($immobile->prezzo ?? '')) !== ''
                            ? (string) ($immobile->prettyPrezzo ?? '')
                            : '',
            excerpt:   '',
            meta:      $meta,
            images:    $images,
        );
    }

    /**
     * @param array<int, object> $items
     * @return array<int, self>
     */
    public static function fromImmobili(array $items): array
    {
        return array_map(
            static fn (object $item): self => self::fromImmobile($item),
            array_values(array_filter($items, 'is_object'))
        );
    }

    /** @param array<string, mixed> $row riga residenza */
    public static function fromResidenza(array $row, ?ResidenzaPresenter $presenter = null): self
    {
        $presenter ??= new ResidenzaPresenter();

        $stato = ResidenzaPresenter::stato($row);

        $timeline = trim(
            ResidenzaPresenter::timelineLabel((int) ($row['inizio_anno'] ?? 0), (int) ($row['inizio_mese'] ?? 0))
            .' → '.
            ResidenzaPresenter::timelineLabel((int) ($row['fine_anno'] ?? 0), (int) ($row['fine_mese'] ?? 0)),
            ' →'
        );

        $meta = [];

        if ($timeline !== '') {
            $meta[] = (object) ['icon' => 'bi bi-calendar3', 'text' => $timeline];
        }

        // Le foto della residenza stanno nella colonna JSON già letta con la
        // riga: la gallery non costa una query in più, quindi si popola sempre.
        $cover = $presenter->cover($row);
        $images = array_values(array_filter(
            array_map(
                static fn (array $img): string => (string) ($img['src'] ?? ''),
                $presenter->previews($row)
            ),
            static fn (string $src): bool => $src !== ''
        ));

        if ($images === [] && $cover !== '') {
            $images = [$cover];
        }

        return new self(
            url:       (string) ($row['url'] ?? '#'),
            cover:     $cover,
            badge:     (object) [
                'label'   => (string) __t('pages.residenze.stato.'.$stato),
                'variant' => 'text-bg-primary',
            ],
            eyebrow:   '',
            title:     (string) ($row['nome'] ?? ''),
            subtitle:  (string) ($row['comune_nome'] ?? ''),
            highlight: '',
            excerpt:   (string) ($row['descrizione_breve'] ?? ''),
            meta:      $meta,
            images:    $images,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, self>
     */
    public static function fromResidenze(array $rows, ?ResidenzaPresenter $presenter = null): array
    {
        $presenter ??= new ResidenzaPresenter();

        return array_map(
            static fn (array $row): self => self::fromResidenza($row, $presenter),
            array_values(array_filter($rows, 'is_array'))
        );
    }
}
