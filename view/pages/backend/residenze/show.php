<?php

use Wonder\Plugin\Immobili\Models\Residenza;
use Wonder\Plugin\Immobili\Catalog\ResidenzaPresenter;
use Wonder\Plugin\Immobili\Support\Forms\ResidenzaForm;

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\{ Container, Card, InfoCard, SectionTitle, RichText, Accordion };
use Wonder\Elements\Media\Swiper;

$row = Residenza::safeFindById($ITEM['id']) ?? [];
$RES = (new ResidenzaPresenter())->present($row);

// --- Helper locali ---------------------------------------------------------

$isTrue = static fn (mixed $value): bool => in_array(
    strtolower(trim((string) $value)),
    ['true', '1', 'si', 'sì', 'yes'],
    true
);

// Etichette leggibili dello stato derivato dal presenter.
$statoLabels = [
    'venduto'    => 'Venduto',
    'in_arrivo'  => 'In arrivo',
    'in_corso'   => 'In corso',
    'completato' => 'Completato',
];
$statoLabel = $statoLabels[$RES->stato] ?? '—';

// Timeline "inizio → fine" (una sola estremità è ammessa).
$timeline = trim(((string) $RES->inizio).' → '.((string) $RES->fine), ' →');
if ($timeline === '') {
    $timeline = '—';
}

$classe = trim((string) ($RES->classe_energetica ?? ''));

// InfoCard incapsulata come nella show immobili (grid a 3 colonne).
$info = static fn (string $title, string|int|float|bool|null $value): Container =>
    (new Container)->components([ new InfoCard($title, $value) ])->noGrid();

// Embed responsivo 16:9 (utility Bootstrap `.ratio`); URL già validato a monte.
$mediaEmbed = static fn (string $url): RichText => new RichText(
    '<div class="ratio ratio-16x9 img-thumbnail overflow-hidden">'
    .'<iframe src="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" style="border:0;" allowfullscreen loading="lazy"></iframe>'
    .'</div>'
);

$badge = static fn (string $label, string $variant): string =>
    "<span class='badge text-bg-{$variant}'>".htmlspecialchars(mb_strtoupper($label), ENT_QUOTES).'</span>';

// --- Mappa (embed Google Maps da lat/lng, come la scheda immobile) ----------

$lat  = trim((string) ($RES->latitudine ?? ''));
$lng  = trim((string) ($RES->longitudine ?? ''));
$zoom = (int) ($RES->zoom ?? 0) ?: 14;
$mapUrl = ($lat !== '' && $lng !== '')
    ? 'https://maps.google.com/maps?q='.rawurlencode($lat.','.$lng).'&z='.$zoom.'&output=embed'
    : '';

// --- Badge di visibilità ----------------------------------------------------

$statoVariant = match ($RES->stato) {
    'venduto'    => 'dark',
    'in_arrivo'  => 'info',
    'in_corso'   => 'primary',
    'completato' => 'success',
    default      => 'secondary',
};

$badges = [ $badge($statoLabel, $statoVariant) ];
$badges[] = $isTrue($RES->visible ?? '') ? $badge('Visibile', 'success') : $badge('Nascosto', 'secondary');
if ($isTrue($RES->evidence ?? '')) {
    $badges[] = $badge('In evidenza', 'warning');
}

// --- Azioni header ----------------------------------------------------------

$actions = [[
    'label'  => 'Guarda',
    'icon'   => 'bi bi-eye',
    'class'  => 'btn-sm btn-info',
    'href'   => $RES->url,
    'target' => '_blank',
]];
if (trim((string) $RES->sito_url) !== '') {
    $actions[] = [
        'label'  => 'Sito web',
        'icon'   => 'bi bi-box-arrow-up-right',
        'class'  => 'btn-sm btn-secondary',
        'href'   => $RES->sito_url,
        'target' => '_blank',
    ];
}
if (trim((string) $RES->capitolatoUrl) !== '') {
    $actions[] = [
        'label'  => 'Capitolato',
        'icon'   => 'bi bi-file-earmark-pdf',
        'class'  => 'btn-sm btn-secondary',
        'href'   => $RES->capitolatoUrl,
        'target' => '_blank',
    ];
}

\Wonder\View\View::layout('backend.show', [
    'TITLE'    => $RES->nome !== '' ? $RES->nome : 'Residenza',
    'SUBTITLE' => $RES->prettyAddress,
    'ACTIONS'  => $actions,
]);

$featureLabels = ResidenzaForm::features();
$selected = array_map('strval', is_array($RES->features) ? $RES->features : []);
$featureCards = [];
foreach ($featureLabels as $id => $label) {
    $featureCards[] = $info($label, in_array((string) $id, $selected, true) ? 'Sì' : 'No');
}

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Container)->components([

            (new Card)->components([
                $RES->imagesAlt !== []
                    ? (new Swiper($RES->imagesAlt))->navigation()->lightbox()->columnSpan(1)
                    : new RichText('<span class="text-muted small">Nessuna immagine caricata.</span>'),
            ])->columns(1),

            (new Card)->components([

                new SectionTitle('Riepilogo'),

                (new Container)->components([
                    $info('Stato', $statoLabel),
                    $info('Timeline', $timeline),
                    $info('Comune', $RES->comune_nome !== '' ? $RES->comune_nome : '—'),

                    $info('Classe energetica', $classe !== '' ? $classe : '—'),
                    $info('Unità abitative', $RES->unita_abitative),
                    $info('Unità commerciali', $RES->unita_commerciali),

                    $info('N° box', $RES->box),
                    $info('Appartamenti disponibili', (int) ($RES->appartamenti_disponibili ?? 0)),
                ])->columns(3),

            ])->columnSpan(2)->columns(1),

            (new Accordion('Descrizione'))->components([
                new RichText(
                    trim((string) $RES->descrizione_lunga) !== ''
                        ? $RES->descrizione_lunga
                        : (trim((string) $RES->descrizione_breve) !== ''
                            ? $RES->descrizione_breve
                            : '<span class="text-muted small">Nessuna descrizione.</span>')
                ),
            ])->expanded(false),

            (new Accordion('Caratteristiche'))->components([
                (new Container)->components($featureCards)->columns(3),
            ])->columnSpan(3)->expanded(false),

        ])->columns(3)->columnSpan(9),

        (new Container)->components([

            (new Card)->components([
                new SectionTitle('Mappa'),
                $mapUrl !== ''
                    ? $mediaEmbed($mapUrl)
                    : new RichText('<span class="text-muted small">Posizione non disponibile.</span>'),
            ])->columns(1),

            (new Card)->components([
                new SectionTitle('Visibilità'),
                new RichText("<div class='d-flex flex-wrap gap-2'>".implode(' ', $badges).'</div>'),
            ])->columns(1),

            (new Card)->components([
                new SectionTitle('Logo'),
                trim((string) $RES->logoUrl) !== ''
                    ? new RichText('<img src="'.htmlspecialchars($RES->logoUrl, ENT_QUOTES, 'UTF-8').'" class="img-fluid img-thumbnail" alt="Logo residenza">')
                    : new RichText('<span class="text-muted small">Nessun logo.</span>'),
            ])->columns(1),

        ])->columns(1)->columnSpan(3),

    ])->columns(12)
);

\Wonder\View\View::end();
