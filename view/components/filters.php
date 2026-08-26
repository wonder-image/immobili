<?php

/**
 * Form filtri della lista (GET). Gli input sono resi tramite FormField
 * (tema Wonder di wonder-image/app), non con HTML custom.
 *
 * Il form può essere avvolto, opzionalmente, nel contenitore a comparsa
 * `wi-dropdown-box` di wonder-image/lib (titolo cliccabile + contenuto che si
 * apre/chiude). Attivabile per progetto senza duplicare il markup.
 *
 * @var array $args  [
 *     'filters'        => array,       // valori correnti dei filtri
 *     'action'         => string,      // action del form (URL lista)
 *     'dropdown'       => bool,        // avvolgi nel wi-dropdown-box (default false)
 *     'dropdown_open'  => bool,        // parte aperto (default false)
 *     'dropdown_title' => string,      // titolo del box (default: traduzione toggle)
 *     'dropdown_class' => string,      // classi extra sul box (es. bg-primary)
 * ]
 */

use Wonder\App\Dependencies;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Plugin\Immobili\Catalog\ImmobileQuery;

Dependencies::autonumeric();

$QUERY = new ImmobileQuery();

$filters = $args['filters'] ?? [];
$action = (string) ($args['action'] ?? '');
$comuni = $QUERY->comuni(false);
$tipologie = $QUERY->tipologie(false);

$v = static fn (string $key): string => (string) ($filters[$key] ?? '');

// Contenitore dropdown opzionale (componente wi-dropdown-box di lib).
$dropdown      = (bool) ($args['dropdown'] ?? false);
$dropdownOpen  = (bool) ($args['dropdown_open'] ?? false);
$dropdownTitle = (string) ($args['dropdown_title'] ?? __t('components.immobili.filters.toggle'));
$dropdownClass = trim((string) ($args['dropdown_class'] ?? ''));

$formClass = 'w-100';
if ($dropdown) {
    $formClass .= ' wi-dropdown-box';
    $formClass .= $dropdownOpen ? ' wi-show' : '';
    $formClass .= $dropdownClass !== '' ? ' '.$dropdownClass : '';
}

// I nomi devono restare sia chiave sia valore dell'opzione: uno spread di una
// lista reindicizzerebbe a chiavi intere (value="0","1",…) rompendo il filtro.
$comuneOptions = [ '' => __t('components.immobili.filters.all') ] + array_combine($comuni, $comuni);
$tipologieOptions = [ '' => __t('components.immobili.filters.all') ] + array_combine($tipologie, $tipologie);


?>
<form method="get" action="<?= e($action) ?>" class="<?= e($formClass) ?>">

    <?php if ($dropdown): ?>
    <div class="wi-dropdown-title wi-switcher">
        <?= e($dropdownTitle) ?> <i class="bi bi-chevron-down"></i>
    </div>
    <div class="wi-dropdown-content">
    <?php endif; ?>

    <div class="d-grid col-8 col-t-2 col-p-1 gap-4">

        <div class="col-2">
            <?= FormField::key('comune')->select($comuneOptions)->label(__t('components.immobili.filters.city'))->value($v('comune')) ?>
        </div>

        <div class="col-2">
            <?= FormField::key('contratto')->select([
                ''  => __t('components.immobili.filters.all'),
                'V' => __t('components.immobili.filters.sale'),
                'A' => __t('components.immobili.filters.rent'),
            ])->label(__t('components.immobili.filters.contract'))->value($v('contratto')) ?>
        </div>

        <?= FormField::key('prezzo_min')->price()->label(__t('components.immobili.filters.price_min'))->value($v('prezzo_min'))->decimal(0) ?>
        <?= FormField::key('prezzo_max')->price()->label(__t('components.immobili.filters.price_max'))->value($v('prezzo_max'))->decimal(0) ?>
        <?= FormField::key('superficie_min')->number()->label(__t('components.immobili.filters.surface_min'))->value($v('superficie_min'))->decimal(0)->symbol('mq') ?>
        <?= FormField::key('superficie_max')->number()->label(__t('components.immobili.filters.surface_max'))->value($v('superficie_max'))->decimal(0)->symbol('mq') ?>
        <?= FormField::key('camere')->number()->label(__t('components.immobili.filters.rooms'))->value($v('camere'))->decimal(0) ?>
        <?= FormField::key('bagni')->number()->label(__t('components.immobili.filters.bathrooms'))->value($v('bagni'))->decimal(0) ?>

        <div class="col-2">
            <?= FormField::key('tipologia')->select($tipologieOptions)->label(__t('components.immobili.filters.type'))->value($v('tipologia')) ?>
        </div>

        <div class="col-2">
            <?= FormField::key('ordina')->select([
                'recenti'         => __t('components.immobili.filters.sort_recent'),
                'prezzo_asc'      => __t('components.immobili.filters.sort_price_asc'),
                'superficie_asc' => __t('components.immobili.filters.sort_surface_asc'),
                'prezzo_desc'     => __t('components.immobili.filters.sort_price_desc'),
                'superficie_desc' => __t('components.immobili.filters.sort_surface_desc'),
            ])->label(__t('components.immobili.filters.sort'))->value($v('ordina') ?: 'recenti') ?>
        </div>

        <a href="<?= e($action) ?>" class="btn btn-outline-primary wi-input-submit a-c"><?= e(__t('components.immobili.filters.reset')) ?></a>
        <button type="submit" class="btn btn-primary wi-input-submit a-c"><?= e(__t('components.immobili.filters.apply')) ?></button>

    </div>

    <?php if ($dropdown): ?>
    </div>
    <?php endif; ?>

</form>
