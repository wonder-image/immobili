<?php

/**
 * Griglia caratteristiche principali della scheda immobile. Stile con classi
 * utility wonder-image/lib.
 *
 * Quali attributi mostrare (e in che ordine) è configurato in backend
 * (Settings → Scheda immobile), con fallback ai default del catalogo. Etichette
 * e valori arrivano da `AttributeCatalog` (fonte condivisa con la scheda PDF).
 *
 * @var array $args ['immobile' => object]
 */

use Wonder\Plugin\Immobili\Models\System\Settings;
use Wonder\Plugin\Immobili\Support\AttributeCatalog;

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

// Chiavi configurate in Settings (fallback ai default 'scheda').
$stored = null;

try {

    $row = Settings::find([], 1);

    if (is_array($row) && isset($row['id'])) {
        $stored = $row['scheda_facts'] ?? null;
    }

} catch (\Throwable) {
    $stored = null;
}

$facts = [];

foreach (AttributeCatalog::selectedKeys($stored, 'scheda') as $key) {
    $value = AttributeCatalog::value($immobile, $key);

    if ($value !== '') {
        $facts[] = ['label' => AttributeCatalog::label($key), 'value' => $value];
    }
}

?>
<div class="w-100 d-grid col-4 col-t-3 col-p-2 gap-6">
    <?php foreach ($facts as $fact) { ?>
        <div class="w-100 bb-1 pb-2">
            <div class="text-small"><?= e((string) $fact['label']) ?></div>
            <div class="text mt-2"><?= e((string) $fact['value']) ?></div>
        </div>
    <?php } ?>
</div>
