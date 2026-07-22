<?php

namespace Wonder\Plugin\Immobili\Feed;

/**
 * DTO di un immobile normalizzato, prodotto da un FeedProvider e consumato dal
 * FeedSyncService per l'upsert nel modello canonico.
 *
 * `fields` contiene le colonne canoniche della tabella `immobili` (esclusi i
 * flag manuali evidence/visible/sold, gestiti dal sync service); `attributi`
 * raccoglie gli attributi estesi/polimorfici; `images` e `descriptions` sono le
 * righe correlate.
 */
final class NormalizedListing
{
    /** Id nativo dell'immobile nel gestionale. */
    public string $externalId = '';

    /** Nome/riferimento leggibile. */
    public string $nome = '';

    /** Data ultima modifica nel gestionale (Y-m-d H:i:s). */
    public string $externalModifiedAt = '';

    /** Data inserimento nel gestionale (Y-m-d H:i:s). */
    public string $createdAt = '';

    /**
     * Override dei flag di pubblicazione forniti dal provider (null = non
     * fornito → il sync applica i default del feed / preserva il valore manuale).
     * `sold`, se fornito, è considerato autoritativo dal feed anche in update.
     */
    public ?string $evidence = null;
    public ?string $sold = null;

    /** @var array<string, mixed> Colonne canoniche di `immobili`. */
    public array $fields = [];

    /** @var array<string, mixed> Attributi estesi/polimorfici (JSON `attributi`). */
    public array $attributi = [];

    /**
     * @var array<int, array<string, mixed>> Righe immagine:
     *   external_id, tipo (F/P), planimetria (true/false), position, titolo,
     *   url, small, medium, large, original.
     */
    public array $images = [];

    /**
     * @var array<int, array<string, mixed>> Righe descrizione:
     *   lingua, titolo, testo, testo_breve.
     */
    public array $descriptions = [];

    public function __construct(string $externalId)
    {
        $this->externalId = $externalId;
    }

    /** Imposta un campo canonico. */
    public function set(string $key, mixed $value): self
    {
        $this->fields[$key] = $value;

        return $this;
    }

    /** Imposta un attributo esteso. */
    public function attribute(string $key, mixed $value): self
    {
        $this->attributi[$key] = $value;

        return $this;
    }

    /** @param array<string, mixed> $image */
    public function addImage(array $image): self
    {
        $this->images[] = $image;

        return $this;
    }

    /** @param array<string, mixed> $description */
    public function addDescription(array $description): self
    {
        $this->descriptions[] = $description;

        return $this;
    }
}
