<?php

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\{ Container, Card, SectionTitle, RichText, Text, Accordion };
use Wonder\Elements\Media\Swiper;

$row = Immobile::safeFindById($ITEM['id']);
$IMMOBILE = (new ImmobilePresenter())->present($row);

\Wonder\View\View::layout('backend.show', [
    'TITLE' => $IMMOBILE->titolo,
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

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Container)->components([

            (new Card)->components([

                new SectionTitle('Visibilità')

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


        ])->columns(3)->columnSpan(8),

        (new Container)->components([

            (new Card)->components([
                new SectionTitle('Mappa')

            ])->columns(1),

            (new Card)->components([

                new SectionTitle('Immagini'),
                (new Container())->components([
                    new Swiper($IMMOBILE->imagesAlt)->navigation()->lightbox()
                ])->columns(1)->columnSpan(1)
                
            ])->columns(1),

            (new Card)->components([
                new SectionTitle('YouTube')

            ])->columns(1),

            (new Card)->components([
                new SectionTitle('Virtual Tour')

            ])->columns(1),

        ])->columns(1)->columnSpan(4)
        
    ])->columns(12)
);

\Wonder\View\View::end();
