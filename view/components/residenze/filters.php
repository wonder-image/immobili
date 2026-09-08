<?php

/** Filtri GET delle residenze, con gli stessi FormField della lista immobili. */
use Wonder\App\ResourceSchema\FormField;
use Wonder\Plugin\Immobili\Catalog\ResidenzaQuery;

$filters = (new ResidenzaQuery())->filters(is_array($args['filters'] ?? null) ? $args['filters'] : []);
$action = (string) ($args['action'] ?? __r('residenze.list'));

?>
<form method="get" action="<?= e($action) ?>" class="w-100">
    <div class="d-grid col-4 col-t-2 col-p-1 gap-4">
        <?= FormField::key('stato')->select([
            '' => __t('components.residenze.filters.all'),
            'in_costruzione' => __t('components.residenze.filters.under_construction'),
            'completata' => __t('components.residenze.filters.completed'),
        ])->label(label: __t('components.residenze.filters.status'))->value($filters['stato']) ?>
        <?= FormField::key('stato_appartamenti')->select([
            '' => __t('components.residenze.filters.all'),
            'disponibili' => __t('components.residenze.filters.available'),
            'non_disponibili' => __t('components.residenze.filters.unavailable'),
        ])->label(__t('components.residenze.filters.apartment_status'))->value($filters['stato_appartamenti']) ?>
        <a href="<?= e($action) ?>" class="btn btn-outline-primary wi-input-submit a-c"><?= e(__t('components.residenze.filters.reset')) ?></a>
        <button type="submit" class="btn btn-primary wi-input-submit a-c"><?= e(__t('components.residenze.filters.apply')) ?></button>
    </div>
</form>
