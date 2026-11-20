<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\TableAuthorization;
use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Support\ListingStatus;

// Cambia lo stato editoriale di un immobile (manuale o da feed) dal menu della
// lista: aggiorna insieme stato, visible e sold. La sincronizzazione dei feed
// preserva questi valori (vedi FeedSyncService).

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = Immobile::$table;
$id = ApiRequest::int('id');
$status = strtolower(ApiRequest::string('stato'));

if ($id <= 0 || !ListingStatus::isValid($status)) {
    ApiRequest::error('Parametri non validi.', 422);
}

if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

if (!RowVisibility::visible($table, $id, RowVisibility::currentUser())) {
    ApiRequest::error('Riga non trovata.', 404);
}

$row = Immobile::findById($id);

if (!is_array($row) || !isset($row['id'])) {
    ApiRequest::error('Riga non trovata.', 404);
}

$values = ListingStatus::values($status, (string) ($row['contratto_id'] ?? ''));

// Update tecnico parziale: Model::update() validerebbe anche i campi required.
Immobile::query()->Update($table, $values, 'id', $id);

ApiRequest::success((string) __t('components.immobili.backend.status.updated'), $values + ['id' => $id]);
