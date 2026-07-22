<?php

/**
 * Form filtri della lista (GET). Gli input sono resi tramite FormField
 * (tema Wonder di wonder-image/app), non con HTML custom.
 *
 * @var array $args  ['filters' => array, 'action' => string]
 */

use Wonder\App\Dependencies;
use Wonder\App\ResourceSchema\FormField;
use Wonder\Plugin\Immobili\Services\ImmobileQuery;

Dependencies::autonumeric();

$QUERY = new ImmobileQuery();

$filters = $args['filters'] ?? [];
$action = (string) ($args['action'] ?? '');
$comuni = $QUERY->comuni(false);

$v = static fn (string $key): string => (string) ($filters[$key] ?? '');

$comuneOptions = ['' => __t('components.immobili.filters.all')];
foreach ($comuni as $nomeComune) {
    $nomeComune = (string) $nomeComune;
    if ($nomeComune !== '') {
        $comuneOptions[$nomeComune] = $nomeComune;
    }
}

?>
<form method="get" action="<?= e($action) ?>" class="w-100">
    <div class="d-grid col-4 col-t-2 col-p-1 gap-4">
        <?= FormField::key('q')->text()->label(__t('components.immobili.filters.search'))->value($v('q'))->render() ?>
        <?= FormField::key('comune')->select($comuneOptions)->searchBar()->label(__t('components.immobili.filters.city'))->value($v('comune'))->render() ?>
        <?= FormField::key('contratto')->select([
            ''  => __t('components.immobili.filters.all'),
            'V' => __t('components.immobili.filters.sale'),
            'A' => __t('components.immobili.filters.rent'),
        ])->label(__t('components.immobili.filters.contract'))->value($v('contratto'))->render() ?>
        <?= FormField::key('tipologia')->text()->label(__t('components.immobili.filters.type'))->value($v('tipologia'))->render() ?>
        <?= FormField::key('prezzo_min')->price()->label(__t('components.immobili.filters.price_min'))->value($v('prezzo_min'))->render() ?>
        <?= FormField::key('prezzo_max')->price()->label(__t('components.immobili.filters.price_max'))->value($v('prezzo_max'))->render() ?>
        <?= FormField::key('superficie_min')->number()->label(__t('components.immobili.filters.surface_min'))->value($v('superficie_min'))->render() ?>
        <?= FormField::key('camere')->number()->label(__t('components.immobili.filters.rooms'))->value($v('camere'))->render() ?>
        <?= FormField::key('ordina')->select([
            'recenti'         => __t('components.immobili.filters.sort_recent'),
            'prezzo_asc'      => __t('components.immobili.filters.sort_price_asc'),
            'prezzo_desc'     => __t('components.immobili.filters.sort_price_desc'),
            'superficie_desc' => __t('components.immobili.filters.sort_surface'),
        ])->label(__t('components.immobili.filters.sort'))->value($v('ordina') ?: 'recenti')->render() ?>
    </div>
    <div class="d-flex gap-3 mt-4">
        <button type="submit" class="btn btn-primary"><?= e(__t('components.immobili.filters.apply')) ?></button>
        <a href="<?= e($action) ?>" class="btn btn-outline-primary"><?= e(__t('components.immobili.filters.reset')) ?></a>
    </div>
</form>
