<?php

declare(strict_types=1);

namespace Wonder\Plugin\Immobili\Pdf;

/**
 * Contatti dell'agenzia mostrati nei PDF, provenienti dai dati aziendali del
 * sito (`$SOCIETY`). Value object immutabile.
 */
final class Contacts
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $tel = '',
        public readonly string $email = '',
        public readonly string $site = '',
        public readonly string $address = '',
    ) {
    }
}
