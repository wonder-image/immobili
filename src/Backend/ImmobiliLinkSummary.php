<?php

namespace Wonder\Plugin\Immobili\Backend;

use Wonder\Backend\Contracts\HomeLinkSummary;

/** Totale e ripartizione degli immobili sul link "Immobili" della home. */
final class ImmobiliLinkSummary implements HomeLinkSummary
{
    public function summary(array $authorities): ?array
    {
        return HomeCounts::immobili(HomeCounts::counts());
    }
}
