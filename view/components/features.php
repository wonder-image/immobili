<?php

/**
 * Griglia caratteristiche principali. Stile con classi utility wonder-image/lib.
 *
 * @var array $args ['immobile' => object]
 */

$immobile = $args['immobile'] ?? null;

if (!is_object($immobile)) {
    return;
}

$facts = [];

if (($immobile->tipologia ?? '') !== '')  { $facts[__t('components.immobili.features.type')] = $immobile->tipologia; }
$facts[__t('components.immobili.features.contract')] = $immobile->contratto ?? '';
if (($immobile->superficie ?? '') !== '')  { $facts[__t('components.immobili.features.surface')] = $immobile->superficie; }
if (($immobile->locali ?? 0) > 0)          { $facts[__t('components.immobili.features.rooms')] = (int) $immobile->locali; }
if (($immobile->camere ?? 0) > 0)          { $facts[__t('components.immobili.features.bedrooms')] = (int) $immobile->camere; }
if (($immobile->bagni ?? 0) > 0)           { $facts[__t('components.immobili.features.bathrooms')] = (int) $immobile->bagni; }
if (($immobile->classe ?? '') !== '')      { $facts[__t('components.immobili.features.energy_class')] = $immobile->classe; }

$attributi = is_array($immobile->attributi ?? null) ? $immobile->attributi : [];
foreach (['piano' => 'Piano', 'anno_costruzione' => 'Anno', 'riscaldamento' => 'Riscaldamento'] as $key => $label) {
    $value = trim((string) ($attributi[$key] ?? ''));
    if ($value !== '') { $facts[$label] = $value; }
}

?>
<div class="w-100 d-grid col-4 col-t-3 col-p-2 gap-6">
    <?php foreach ($facts as $label => $value) {
        if ((string) $value === '') { continue; }
        ?>
        <div class="w-100 bb-1 pb-2">
            <div class="text-small"><?= e((string) $label) ?></div>
            <div class="text mt-2"><?= e((string) $value) ?></div>
        </div>
    <?php } ?>
</div>
