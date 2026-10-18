<?php

namespace Wonder\Plugin\Immobili\Backend;

use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Elements\Components\StatusCard;
use Wonder\Plugin\Immobili\Resources\ImmobileResource;

/** Immobili del catalogo nella striscia di stato della home, per chi vede l'elenco. */
final class ImmobiliStatus implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        if (!ImmobileResource::allows($authorities, 'list')) {
            return null;
        }

        $summary = HomeCounts::immobili(HomeCounts::counts());

        return StatusCard::make('Immobili', $summary['value'])
            ->icon('bi-house-door')
            ->detail($summary['detail'])
            ->href(rtrim($backendUrl, '/').'/'.ImmobileResource::path().'/');
    }

    public function order(): int
    {
        return 30;
    }
}
