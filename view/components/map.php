<?php

    /**
     * Mappa Google degli immobili.
     *
     * Args:
     *  - features: array di Feature GeoJSON (vedi ImmobilePresenter::geoJson())
     *  - zoom:     zoom iniziale (default 15, usato con una sola feature)
     *  - mapId:    id del contenitore (default generato, consente più mappe per pagina)
     *
     * Richiede `google_maps_api_key` nel config del modulo (override del sito
     * in custom/config/modules/immobili.php); senza chiave il contenitore
     * resta vuoto. Css e js sono asset del modulo risolti con
     * Immobili::asset(): un sito può personalizzarli pubblicandoli con
     * `php forge publish:module immobili --assets`.
     */

    use Wonder\Plugin\Immobili\Immobili;

    $mapId = $args['mapId'] ?? code('7', 'all', 'map_');
    $POINTS_JSON = $args['features'] ?? [];
    $POINTS_JSON = array_values(array_filter(is_array($POINTS_JSON) ? $POINTS_JSON : [], 'is_array'));

    if ($POINTS_JSON === []) { return; }

    $GEO_JSON = [];
    $GEO_JSON['type'] = 'FeatureCollection';
    $GEO_JSON['features'] = $POINTS_JSON;

    $zoom = (int) ($args['zoom'] ?? 0) ?: 15;

    $apiKey = trim((string) Immobili::config('google_maps_api_key', ''));
    $gmapId = trim((string) Immobili::config('google_maps_map_id', ''));

    $css = Immobili::asset('css/immobili-map.css');
    $js = Immobili::asset('js/immobili-map.js');

?>

    <?php if ($css !== '') { ?>
        <link rel="stylesheet" href="<?=e($css)?>">
    <?php } ?>

    <div id="<?= e($mapId) ?>" class="immobili-map w-100 h-100 skeleton"
        data-api-key="<?=e($apiKey)?>"
        <?php if ($gmapId !== '') { ?>data-map-id="<?=e($gmapId)?>"<?php } ?>
        data-zoom="<?=e($zoom)?>"></div>

    <?php if ($js !== '') { ?>
        <script src="<?=e($js)?>"></script>
    <?php } ?>

    <script>

        initMap(<?=js_e($mapId)?>, <?=js_e($GEO_JSON)?>, { zoom: <?=js_e($zoom)?> });

    </script>
