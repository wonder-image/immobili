<?php

use Wonder\Localization\LanguageContext;
use Wonder\Plugin\Immobili\Feed\ProviderRegistry;
use Wonder\Plugin\Immobili\Immobili;

// Registra il path delle traduzioni del modulo (urls.json bilingua it/en +
// pages/components/immobili/pdf). Abilita __t() e __r() a risolvere le chiavi
// del modulo e gli slug localizzati delle route.
LanguageContext::addUrlsPath(Immobili::langPath());

// Registra i provider (gestionali) di default. Un sito o un altro modulo può
// aggiungerne altri chiamando ProviderRegistry::register(...).
if (class_exists(ProviderRegistry::class)) {
    ProviderRegistry::registerDefaults();
}

return [
    'default_locale_fallback' => 'it',

    // Fallback legacy per Google Maps. Le credenziali framework
    // GCP_CLIENT_API_KEY / G_MAPS_MAP_ID hanno la precedenza.
    'google_maps_api_key' => '',
    'google_maps_map_id' => '',

    // Aspetto compatto del marker: `icon` oppure `icon-price`.
    'map_marker_mode' => 'icon-price',
];
