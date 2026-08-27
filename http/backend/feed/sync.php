<?php

use Wonder\Plugin\Immobili\Sync\FeedSyncService;

/**
 * Trigger backend "Sincronizza ora".
 *
 * - /backend/immobili-feed/{id}/sync/ sincronizza un singolo feed;
 * - /backend/immobili/sync/ sincronizza tutti i feed attivi.
 */

$id = (int) ($GLOBALS['ROUTE_PARAMETERS']['id'] ?? 0);
$service = new FeedSyncService();

if ($id > 0) {
    $results = [$service->syncById($id)];
    $back = __r('backend.resource.immobili-feed.list');
} else {
    $results = $service->syncAll();
    $back = __r('backend.resource.immobili.list');
}

$success = $results !== [];

foreach ($results as $result) {
    if (empty($result['success'])) {
        $success = false;
        break;
    }
}

$alert = $success ? 680 : 681;
$back = $back !== '' ? $back : ($id > 0 ? '/backend/immobili-feed/' : '/backend/immobili/');

header('Location: '.$back.'?alert='.$alert, true, 302);
exit;
