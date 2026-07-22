<?php

namespace Wonder\Plugin\Immobili\Feed\Contracts;

use Wonder\Plugin\Immobili\Feed\FeedSourceConfig;

/**
 * Contratto di un provider/gestionale immobiliare.
 *
 * Un provider sa (a) sincronizzare le proprie tassonomie nelle tabelle
 * `immobili_*`, (b) leggere gli immobili dal feed e restituirli normalizzati.
 * Aggiungere un nuovo gestionale = implementare questa interfaccia e registrarla
 * nel ProviderRegistry.
 */
interface FeedProvider
{
    /** Chiave stabile del provider (es. "getrix", "gestim"). */
    public function key(): string;

    /** Etichetta leggibile (es. "Getrix"). */
    public function label(): string;

    /**
     * Campi di configurazione specifici del provider da mostrare nel form del
     * feed in backend (array di Wonder\App\ResourceSchema\FormField).
     *
     * @return array<int, mixed>
     */
    public function configSchema(): array;

    /**
     * Sincronizza/aggiorna le tassonomie del provider (categorie, tipologie,
     * geografia) nelle tabelle `immobili_*`, scoping per `provider`.
     */
    public function syncTaxonomies(FeedSourceConfig $feed): void;

    /**
     * Legge gli immobili dal feed e li restituisce normalizzati.
     *
     * @return iterable<int, \Wonder\Plugin\Immobili\Feed\NormalizedListing>
     */
    public function fetchListings(FeedSourceConfig $feed): iterable;
}
