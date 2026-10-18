<?php

namespace Wonder\Plugin\Immobili\Backend;

use Wonder\Backend\Contracts\HomeStatusItem;
use Wonder\Elements\Components\StatusCard;
use Wonder\Plugin\Immobili\Resources\ResidenzaResource;

/** Residenze nella striscia di stato della home, per chi vede l'elenco. */
final class ResidenzeStatus implements HomeStatusItem
{
    public function status(array $authorities, string $backendUrl): ?StatusCard
    {
        if (!ResidenzaResource::allows($authorities, 'list')) {
            return null;
        }

        $summary = HomeCounts::residenze(HomeCounts::counts());

        return StatusCard::make('Residenze', $summary['value'])
            ->icon('bi-buildings')
            ->detail($summary['detail'])
            ->href(rtrim($backendUrl, '/').'/'.ResidenzaResource::path().'/');
    }

    public function order(): int
    {
        return 40;
    }
}
