<?php

/**
 * Layout principale del modulo Immobili.
 *
 * Chaina su `frontend.base` e stampa il body della pagina. Lo stile usa
 * esclusivamente le classi utility di wonder-image/lib (nessun CSS del modulo).
 * Sovrascrivibile in `custom/modules/immobili/view/layout/frontend/`.
 */

\Wonder\View\View::layout('frontend.main');

?>

<?= $PAGE_CONTENT ?>

<?php \Wonder\View\View::end(); ?>
