<?php

use Wonder\Plugin\Immobili\Services\IdealistaExporter;

/**
 * Feed XML Idealista (formato di pubblicazione ads/ad).
 *
 * Rotta: /immobili/idealista/. Il crawler di Idealista scarica questo URL per
 * pubblicare gli immobili sul portale. È una risposta XML autonoma.
 */

header('Content-Type: application/xml; charset=utf-8');

echo (new IdealistaExporter())->xml();

exit;
