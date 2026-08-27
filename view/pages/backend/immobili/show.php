<?php

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Catalog\ImmobilePresenter;

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\{ Container, Card, InfoCard, SectionTitle, RichText, Accordion };
use Wonder\Elements\Media\Swiper;

$row = Immobile::safeFindById($ITEM['id']);
$IMMOBILE = (new ImmobilePresenter())->present($row);

\Wonder\View\View::layout('backend.show', [
    'TITLE' => $IMMOBILE->nome,
    'SUBTITLE' => $IMMOBILE->prettyName,
    'ACTIONS' => [
        [
            'label' => 'Stampa',
            'icon' => 'bi bi-printer',
            'class' => 'btn-sm btn-secondary',
            'items' => [
                [ 'label' => 'Scheda immobile', 'href' => $IMMOBILE->url_scheda, 'target' => '_blank' ],
                [ 'label' => 'Cartello immobile', 'href' => $IMMOBILE->url_cartello, 'target' => '_blank' ],
                [ 'label' => 'Cartello vetrina', 'href' => $IMMOBILE->url_cartello_vetrina, 'target' => '_blank' ],
                [ 'label' => 'Cartello vetrina (Venduto)', 'href' => $IMMOBILE->url_cartello_vetrina_venduto, 'target' => '_blank' ],
                [ 'kind' => 'divider' ],
                [ 'label' => 'QR Code', 'href' => $IMMOBILE->qrcode ]
            ]
        ], [
            'label' => 'Guarda',
            'icon' => 'bi bi-eye',
            'class' => 'btn-sm btn-info',
            'href' => $IMMOBILE->url,
            'target' => '_blank'
        ]
    ]
]);

// URL media validi (schema http/https o relativo), ordine del DB preservato.
// Le card YouTube / Virtual Tour li rendono inline (vedi sotto).
$mediaUrls = static function (mixed $urls): array {
    return array_values(array_filter(
        is_array($urls) ? $urls : [],
        static function ($url): bool {
            $url = trim((string) $url);

            if ($url === '') {
                return false;
            }

            $scheme = parse_url($url, PHP_URL_SCHEME);

            return $scheme === null || in_array(strtolower((string) $scheme), ['http', 'https'], true);
        }
    ));
};

$youtubeUrls     = $mediaUrls($IMMOBILE->youtube ?? null);
$virtualTourUrls = $mediaUrls($IMMOBILE->virtual_tour ?? null);

// Embed responsivo 16:9 (utility Bootstrap `.ratio`). L'URL è già validato
// (schema http/https/relativo) da $mediaUrls; qui viene solo escapato.
$mediaEmbed = static fn (string $url, string $extra = ''): RichText => new RichText(
    '<div class="ratio ratio-16x9 img-thumbnail overflow-hidden'.($extra !== '' ? ' '.$extra : '').'">'
    .'<iframe src="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" style="border:0;" allowfullscreen loading="lazy"></iframe>'
    .'</div>'
);

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Container)->components([

            (new Card)->components([

                (new Swiper($IMMOBILE->imagesAlt))->navigation()->lightbox()->columnSpan(1)


            ])->columns(1),

            (new Card)->components([

                new SectionTitle('Contratto'),
                new RichText(
                    "Tipologia: {$IMMOBILE->contratto}<br>"
                    ."Prezzo: {$IMMOBILE->prettyPrezzo}<br>"
                    ."Spese mensili: {$IMMOBILE->prettySpeseMensili}"
                )

            ])->columns(1),

            (new Card)->components([

                new SectionTitle('Proprietà'),
                new RichText(
                    "Tipo: {$IMMOBILE->tipologia}<br>"
                    ."Superficie: {$IMMOBILE->prettySuperficie}<br>"
                    ."N°Locali: {$IMMOBILE->locali}"
                )

            ])->columns(1),

            (new Accordion('Descrizione'))->components([
                
                new RichText($IMMOBILE->descrizione)

            ])->expanded(false),

            (new Accordion("Caratteristiche dell'immobile"))->components([

                (new Container)->components([

                    (new Container)->components([
                        new InfoCard('Anno di costruzione', $IMMOBILE->anno_costruzione),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Classe immobile', $IMMOBILE->classe_immobile),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Stato immobile', $IMMOBILE->stato_immobile),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Piano', $IMMOBILE->piano),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Numero piani stabile', $IMMOBILE->numero_piani_stabile),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Ascensore', $IMMOBILE->ascensore),
                    ])->noGrid(),

                ])->columns(3)

            ])->columnSpan(3)->expanded(false),

            (new Accordion("Composizione dell'immobile"))->components([

                (new Container)->components([

                    (new Container)->components([
                        new InfoCard('Camere da letto', $IMMOBILE->camere),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Altre Camere/Stanze', $IMMOBILE->altre_camere),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Totale Camere/Stanze', $IMMOBILE->totale_camere),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Bagni', $IMMOBILE->bagni),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Cucina', $IMMOBILE->cucina),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Giardino', $IMMOBILE->giardino),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Box Auto', $IMMOBILE->box_auto),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Posto Auto', $IMMOBILE->posti_auto),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Balcone', $IMMOBILE->balcone),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Terrazzo', $IMMOBILE->terrazzo),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Cantina', $IMMOBILE->cantina),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Mansarda', $IMMOBILE->mansarda),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Taverna', $IMMOBILE->taverna),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Arredamento', $IMMOBILE->arredamento),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Infissi esterni', $IMMOBILE->infissi_esterni),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Impianto TV', $IMMOBILE->impianto_tv),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Portineria', $IMMOBILE->portineria),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Porta blindata', $IMMOBILE->porta_blindata),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard("Impianto d'allarme", $IMMOBILE->impianto_allarme),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Cancello elettrico', $IMMOBILE->cancello_elettrico),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Videocitofono', $IMMOBILE->videocitofono),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Fibra ottica', $IMMOBILE->fibra_ottica),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Camino', $IMMOBILE->camino),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Idromassaggio', $IMMOBILE->idromassaggio),
                    ])->noGrid(),

                    (new Container)->components([
                        new InfoCard('Piscina', $IMMOBILE->piscina),
                    ])->noGrid(),
                    (new Container)->components([
                        new InfoCard('Campo da tennis', $IMMOBILE->campo_tennis),
                    ])->noGrid(),

                ])->columns(3)

            ])->columnSpan(3)->expanded(false),


        ])->columns(3)->columnSpan(9),

        (new Container)->components([

            (new Card)->components([

                new SectionTitle('Mappa'),

                ($IMMOBILE->gmaps ?? '') !== ''
                    ? $mediaEmbed($IMMOBILE->gmaps)
                    : new RichText('<span class="text-muted small">Posizione non disponibile.</span>')

            ])->columns(1),

            (new Card)->components([

                new SectionTitle('Visibilità'),

                new RichText(Immobile::statusBadges($row) ?: '<span class="text-muted small">Nessuno stato.</span>')

            ])->columns(1),

            (new Card)->components([

                new SectionTitle('YouTube'),

                ...($youtubeUrls !== []
                    ? array_map(
                        static fn (string $url) => $mediaEmbed($url, 'mt-3'),
                        $youtubeUrls
                    )
                    : [ new RichText('<span class="text-muted small">Nessun video YouTube.</span>') ])

            ])->columns(1),

            (new Card)->components([

                new SectionTitle('Virtual Tour'),

                ...($virtualTourUrls !== []
                    ? array_map(
                        static fn (string $url) => $mediaEmbed($url, 'mt-3'),
                        $virtualTourUrls
                    )
                    : [ new RichText('<span class="text-muted small">Nessun virtual tour.</span>') ])

            ])->columns(1),

        ])->columns(1)->columnSpan(3)
        
    ])->columns(12)
);

\Wonder\View\View::end();
