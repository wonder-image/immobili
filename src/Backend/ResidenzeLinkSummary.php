<?php

namespace Wonder\Plugin\Immobili\Backend;

use Wonder\Backend\Contracts\HomeLinkSummary;

/** Totale, visibili e in evidenza sul link "Residenze" della home. */
final class ResidenzeLinkSummary implements HomeLinkSummary
{
    public function summary(array $authorities): ?array
    {
        return HomeCounts::residenze(HomeCounts::counts());
    }
}
