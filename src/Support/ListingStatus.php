<?php

namespace Wonder\Plugin\Immobili\Support;

/**
 * Stato editoriale di un immobile e flag derivati.
 *
 * `stato` (active/suspended/purchased/rented) è il valore canonico del form;
 * il frontend legge `visible` e `sold`. Ogni scrittura passa da qui perché i
 * tre campi restino coerenti (form, menu della lista, sincronizzazione feed).
 *
 * Contiene anche la politica per gli immobili non più presenti nel feed
 * (`feed_vanished` nella configurazione del modulo):
 * - `delete` (default): eliminati, tranne quelli già venduti/affittati;
 * - `retain`: conservati; in vendita → nascosti, in affitto → affittati.
 *   Se tornano nel feed riprendono lo stato disponibile.
 */
final class ListingStatus
{
    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    public const PURCHASED = 'purchased';
    public const RENTED = 'rented';

    public const ALL = [self::ACTIVE, self::SUSPENDED, self::PURCHASED, self::RENTED];

    public const VANISHED_DELETE = 'delete';
    public const VANISHED_RETAIN = 'retain';

    /** Esiti di vanished(): elimina la riga oppure lasciala com'è. */
    public const DELETE = 'delete';
    public const KEEP = 'keep';

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    /** @return array{0: string, 1: string} [visible, sold] */
    public static function flags(string $status): array
    {
        return match ($status) {
            self::SUSPENDED => ['false', 'false'],
            self::PURCHASED, self::RENTED => ['true', 'true'],
            default => ['true', 'false'],
        };
    }

    /**
     * Campi da salvare per uno stato. Venduto/affittato seguono il contratto:
     * chiedere "venduto" su un affitto salva "affittato" e viceversa.
     *
     * @return array{stato: string, visible: string, sold: string}
     */
    public static function values(string $status, string $contract = ''): array
    {
        if ($status === self::PURCHASED || $status === self::RENTED) {
            $status = self::soldStatus($contract);
        }

        [$visible, $sold] = self::flags($status);

        return ['stato' => $status, 'visible' => $visible, 'sold' => $sold];
    }

    /** Stato "venduto" per il contratto: affitto → rented, altrimenti purchased. */
    public static function soldStatus(string $contract): string
    {
        return strtoupper(trim($contract)) === 'A' ? self::RENTED : self::PURCHASED;
    }

    /**
     * Stato da valori di un form: `stato` se valido, altrimenti derivato dai
     * vecchi flag (record legacy senza stato).
     *
     * @param array<string, mixed> $values
     */
    public static function fromValues(array $values): string
    {
        $status = strtolower(trim((string) ($values['stato'] ?? '')));

        return self::isValid($status) ? $status : self::fromFlags($values);
    }

    /**
     * Stato di una riga salvata: `stato` se è coerente con i flag, altrimenti
     * derivato dai flag, che sono ciò che il sito mostra (es. dopo il toggle
     * "Visibile" della lista, che cambia solo `visible`).
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): string
    {
        $status = strtolower(trim((string) ($row['stato'] ?? '')));

        if (!array_key_exists('visible', $row) && !array_key_exists('sold', $row)) {
            return self::isValid($status) ? $status : self::ACTIVE;
        }

        if (self::isValid($status) && self::flags($status) === [self::bool($row['visible'] ?? 'true'), self::bool($row['sold'] ?? 'false')]) {
            return $status;
        }

        return self::fromFlags($row);
    }

    /** @param array<string, mixed> $row */
    public static function fromFlags(array $row): string
    {
        if (self::bool($row['sold'] ?? 'false') === 'true') {
            return self::soldStatus((string) ($row['contratto_id'] ?? ''));
        }

        if (array_key_exists('visible', $row) && self::bool($row['visible']) === 'false') {
            return self::SUSPENDED;
        }

        return self::ACTIVE;
    }

    public static function vanishedPolicy(mixed $value): string
    {
        return strtolower(trim((string) $value)) === self::VANISHED_RETAIN
            ? self::VANISHED_RETAIN
            : self::VANISHED_DELETE;
    }

    /**
     * Cosa fare di un immobile non più presente nel feed.
     *
     * Con `retain` la regola si applica solo quando l'immobile sparisce
     * ($newlyVanished): nei sync successivi non sovrascrive le modifiche fatte
     * a mano dal backend.
     *
     * @param array<string, mixed> $row
     * @return string|array{stato: string, visible: string, sold: string} DELETE, KEEP o i campi da salvare
     */
    public static function vanished(array $row, string $policy, bool $newlyVanished = true): string|array
    {
        $sold = self::bool($row['sold'] ?? 'false') === 'true';

        if (self::vanishedPolicy($policy) === self::VANISHED_DELETE) {
            return $sold ? self::KEEP : self::DELETE;
        }

        if (!$newlyVanished) {
            return self::KEEP;
        }

        $contract = strtoupper(trim((string) ($row['contratto_id'] ?? '')));

        if ($contract === 'A') {
            return self::values(self::RENTED, 'A');
        }

        // In vendita: nascosto. Un immobile già venduto resta tra i venduti.
        return $sold ? self::KEEP : self::values(self::SUSPENDED, $contract);
    }

    /**
     * Campi da ripristinare quando un immobile sparito torna nel feed; [] se
     * non c'è nulla da cambiare. Riporta disponibile solo lo stato che la
     * regola `retain` aveva impostato (vendita nascosta, affitto affittato).
     *
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    public static function reappeared(array $row, string $policy): array
    {
        if (self::vanishedPolicy($policy) !== self::VANISHED_RETAIN) {
            return [];
        }

        $status = self::fromRow($row);
        $contract = strtoupper(trim((string) ($row['contratto_id'] ?? '')));
        $restore = $contract === 'A' ? $status === self::RENTED : $status === self::SUSPENDED;

        return $restore ? self::values(self::ACTIVE, $contract) : [];
    }

    private static function bool(mixed $value): string
    {
        return in_array(strtolower(trim((string) $value)), ['true', '1', 'yes', 'on'], true) ? 'true' : 'false';
    }
}
