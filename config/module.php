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

    // Google Maps JS API (componente view/components/map.php). Il sito imposta
    // la chiave in custom/config/modules/immobili.php; senza chiave la mappa
    // non viene inizializzata. `google_maps_map_id` è il Map ID vettoriale
    // (console Google Cloud), default DEMO_MAP_ID lato js.
    'google_maps_api_key' => '',
    'google_maps_map_id' => '',
];
