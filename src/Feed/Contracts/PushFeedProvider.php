<?php

namespace Wonder\Plugin\Immobili\Feed\Contracts;

/**
 * Contratto marcatore per i provider push (es. Gestim).
 *
 * È il gestionale a chiamare l'endpoint di sincronizzazione passando il file
 * del feed: senza quella richiesta non c'è niente da leggere. La
 * sincronizzazione pianificata e "tutti i feed" li saltano.
 */
interface PushFeedProvider
{
}
