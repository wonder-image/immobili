/**
 * Mappa immobili — inizializzazione Google Maps dal GeoJSON del modulo.
 *
 * Uso (dal componente view/components/map.php):
 *
 *     initMap('immobili-google-maps', GEO_JSON, { zoom: 15 });
 *
 * Il contenitore deve esporre `data-api-key` (obbligatorio, chiave Google
 * Maps JS API) ed eventualmente `data-map-id` (Map ID vettoriale, default
 * DEMO_MAP_ID) e `data-zoom`. L'API viene caricata dinamicamente una sola
 * volta; senza chiave la mappa non viene inizializzata e il contenitore
 * resta vuoto (warning in console).
 *
 * Ogni feature è un Point GeoJSON con properties { id, name, price,
 * surface, url, cover } (vedi ImmobilePresenter::geoJson()).
 */

(function () {
    'use strict';

    var pending = [];
    var apiState = 'idle'; // idle | loading | ready | failed

    function parseGeoJson(geoJson) {
        if (typeof geoJson === 'string') {
            try {
                geoJson = JSON.parse(geoJson);
            } catch (error) {
                return [];
            }
        }

        if (!geoJson || typeof geoJson !== 'object') {
            return [];
        }

        var features = Array.isArray(geoJson.features) ? geoJson.features : [geoJson];

        return features.filter(function (feature) {
            var coords = feature
                && feature.geometry
                && feature.geometry.type === 'Point'
                && feature.geometry.coordinates;

            return Array.isArray(coords)
                && isFinite(coords[0])
                && isFinite(coords[1]);
        });
    }

    function loadApi(apiKey) {
        if (apiState === 'ready' || (window.google && window.google.maps)) {
            apiState = 'ready';
            flush();
            return;
        }

        if (apiState === 'loading') {
            return;
        }

        apiState = 'loading';

        window.__immobiliMapsReady = function () {
            apiState = 'ready';
            flush();
        };

        var script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js'
            + '?key=' + encodeURIComponent(apiKey)
            + '&v=weekly&libraries=marker&loading=async'
            + '&callback=__immobiliMapsReady';
        script.async = true;
        script.onerror = function () {
            apiState = 'failed';
            console.warn('[immobili] Caricamento Google Maps JS API fallito.');
            pending = [];
        };

        document.head.appendChild(script);
    }

    function flush() {
        var queue = pending;
        pending = [];
        queue.forEach(render);
    }

    function markerContent(properties) {
        var content = document.createElement('div');
        content.className = 'property';

        var icon = document.createElement('div');
        icon.className = 'icon';
        icon.innerHTML = '<i class="bi bi-house-door"></i>';
        content.appendChild(icon);

        var details = document.createElement('div');
        details.className = 'details';

        var name = document.createElement('a');
        name.className = 'name';
        name.textContent = String(properties.name || '');
        if (properties.url) {
            name.href = String(properties.url);
        }
        details.appendChild(name);

        var features = document.createElement('div');
        features.className = 'features';

        [properties.price, properties.surface].forEach(function (value) {
            if (!value) {
                return;
            }
            var span = document.createElement('span');
            span.textContent = String(value);
            features.appendChild(span);
        });

        details.appendChild(features);
        content.appendChild(details);

        return content;
    }

    function render(job) {
        var element = job.element;
        var features = job.features;
        var maps = window.google.maps;

        maps.importLibrary('marker').then(function (markerLib) {
            var bounds = new maps.LatLngBounds();

            features.forEach(function (feature) {
                var coords = feature.geometry.coordinates;
                bounds.extend({ lat: Number(coords[1]), lng: Number(coords[0]) });
            });

            var map = new maps.Map(element, {
                center: bounds.getCenter(),
                zoom: job.zoom,
                mapId: job.gmapId,
                clickableIcons: false,
                mapTypeControl: false,
                streetViewControl: false,
            });

            var markers = [];

            features.forEach(function (feature) {
                var coords = feature.geometry.coordinates;
                var properties = feature.properties || {};
                var content = markerContent(properties);

                var marker = new markerLib.AdvancedMarkerElement({
                    map: map,
                    position: { lat: Number(coords[1]), lng: Number(coords[0]) },
                    content: content,
                    title: String(properties.name || ''),
                    gmpClickable: true,
                });

                marker.addListener('click', function () {
                    var active = content.classList.contains('highlight');

                    markers.forEach(function (other) {
                        other.content.classList.remove('highlight');
                        other.zIndex = null;
                    });

                    if (!active) {
                        content.classList.add('highlight');
                        marker.zIndex = 1;
                    }
                });

                markers.push(marker);
            });

            if (features.length > 1) {
                map.fitBounds(bounds);
            }

            maps.event.addListenerOnce(map, 'tilesloaded', function () {
                element.classList.remove('skeleton');
            });
        });
    }

    window.initMap = function (mapId, geoJson, options) {
        options = options || {};

        var element = document.getElementById(mapId);

        if (!element || element.dataset.immobiliMap === 'done') {
            return;
        }

        var features = parseGeoJson(geoJson);

        if (features.length === 0) {
            element.classList.remove('skeleton');
            return;
        }

        var apiKey = String(options.apiKey || element.dataset.apiKey || '');

        if (apiKey === '') {
            console.warn('[immobili] Google Maps API key mancante: configura google_maps_api_key nel config del modulo.');
            element.classList.remove('skeleton');
            return;
        }

        element.dataset.immobiliMap = 'done';

        pending.push({
            element: element,
            features: features,
            zoom: parseInt(options.zoom || element.dataset.zoom, 10) || 15,
            gmapId: String(options.gmapId || element.dataset.mapId || 'DEMO_MAP_ID'),
        });

        if (apiState === 'ready') {
            flush();
        } else {
            loadApi(apiKey);
        }
    };
})();
