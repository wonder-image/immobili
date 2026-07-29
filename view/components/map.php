<?php

/**
 * Mappa Google degli immobili.
 *
 * Args:
 *  - features:    Feature GeoJSON Point (vedi ImmobilePresenter::geoJson())
 *  - zoom:        zoom iniziale, default 15
 *  - mapId:       id HTML del contenitore, generato se assente
 *  - googleMapId: override opzionale del Google Map ID
 *  - height:      altezza CSS o pixel, default 420
 *  - markerMode:  `icon` oppure `icon-price`, default dal config del modulo
 *
 * L'Element GoogleMap dell'app gestisce loader, MapManager, fallback senza
 * Google Map ID e istanze multiple. Questo componente aggiunge soltanto il
 * renderer del marker specifico del dominio immobili.
 */

use Wonder\App\Support\GoogleMaps;
use Wonder\Elements\Media\GoogleMap;
use Wonder\Plugin\Immobili\Immobili;

$features = $args['features'] ?? [];
$features = array_values(array_filter(
    is_array($features) ? $features : [],
    static fn (mixed $feature): bool => is_array($feature)
));

if ($features === []) {
    return;
}

$normalizeMarkerMode = static function (mixed $value): ?string {
    if (!is_scalar($value)) {
        return null;
    }

    $value = strtolower(trim((string) $value));
    $value = str_replace(['_', '+', ' '], '-', $value);

    return in_array($value, ['icon', 'icon-price'], true) ? $value : null;
};

$markerMode = $normalizeMarkerMode(
    $args['markerMode'] ?? Immobili::config('map_marker_mode', 'icon-price')
) ?? 'icon-price';

// Ogni Feature può sovrascrivere la modalità globale tramite
// properties.markerMode; i valori non validi ricadono sull'opzione mappa.
$features = array_map(
    static function (array $feature) use ($markerMode, $normalizeMarkerMode): array {
        $properties = is_array($feature['properties'] ?? null)
            ? $feature['properties']
            : [];

        $properties['markerMode'] = $normalizeMarkerMode($properties['markerMode'] ?? null)
            ?? $markerMode;
        $feature['properties'] = $properties;

        return $feature;
    },
    $features
);

$containerId = is_scalar($args['mapId'] ?? null)
    ? trim((string) $args['mapId'])
    : '';
$containerId = $containerId !== '' ? $containerId : code('7', 'all', 'map_');

$zoom = (int) ($args['zoom'] ?? 0);
$zoom = $zoom > 0 ? $zoom : 15;
$height = $args['height'] ?? 420;

$moduleApiKey = trim((string) Immobili::config('google_maps_api_key', ''));
$moduleMapId = trim((string) Immobili::config('google_maps_map_id', ''));
$argumentMapId = is_scalar($args['googleMapId'] ?? null)
    ? trim((string) $args['googleMapId'])
    : '';

$frameworkApiKey = GoogleMaps::apiKey();
$frameworkMapId = GoogleMaps::mapId();

// Le credenziali framework sono la sorgente canonica; le vecchie opzioni del
// modulo restano come fallback per i siti non ancora migrati.
$apiKey = $frameworkApiKey !== '' ? $frameworkApiKey : $moduleApiKey;
$googleMapId = $argumentMapId !== ''
    ? $argumentMapId
    : ($frameworkMapId !== '' ? $frameworkMapId : $moduleMapId);

$css = Immobili::asset('css/immobili-map.css');
$js = Immobili::asset('js/immobili-map.js');

$map = GoogleMap::fromGeoJson([
    'type' => 'FeatureCollection',
    'features' => $features,
])
    ->id($containerId)
    ->apiKey($apiKey)
    ->zoom($zoom)
    ->height(is_int($height) || is_string($height) ? $height : 420)
    ->mapType('roadmap')
    ->labels()
    ->fitBounds(count($features) > 1
        ? ['padding' => 40, 'maxZoom' => $zoom]
        : false)
    ->addClass('immobili-map');

if ($googleMapId !== '') {
    $map->mapId($googleMapId);
}

if ($js !== '') {
    $map->markerRenderer('ImmobiliMaps.markerContent')->highlightMarkers();
}

?>

<?php if ($css !== '') { ?>
    <link rel="stylesheet" href="<?=e($css)?>">
<?php } ?>

<?php if ($js !== '') { ?>
    <script src="<?=e($js)?>"></script>
<?php } ?>

<?=$map->render()?>
