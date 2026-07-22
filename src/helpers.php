<?php

/**
 * Helper globali del modulo Immobili.
 *
 * Sono funzioni pure, senza dipendenze dal runtime del framework, così restano
 * testabili in isolamento (vedi tests/smoke.php). Prefisso `immobili*`.
 */

if (!function_exists('immobiliIsTrue')) {
    /**
     * Normalizza un valore "boolean-like" del feed/DB (1/true/yes/on/"true").
     */
    function immobiliIsTrue(mixed $value): bool
    {
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'on', 'si', 'sì'],
            true
        );
    }
}

if (!function_exists('immobiliDecodeJsonArray')) {
    /**
     * Decodifica sicura di un valore JSON in array (ritorna [] se non valido).
     *
     * @return array<int|string, mixed>
     */
    function immobiliDecodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('immobiliFormatPrice')) {
    /**
     * Formatta un prezzo in euro senza decimali (separatore migliaia ".").
     * Ritorna stringa vuota per valori non positivi.
     */
    function immobiliFormatPrice(mixed $value, string $symbol = '€'): string
    {
        $amount = (int) round((float) $value);

        if ($amount <= 0) {
            return '';
        }

        return $symbol.' '.number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('immobiliQrCodeRelativePath')) {
    /**
     * Path relativo (dentro upload) del QR di un immobile, o '' se non
     * determinabile. Il QR viene generato al sync in questa posizione, ma NON è
     * mai salvato nel DB: si ricostruisce in modo deterministico dall'external_id.
     */
    function immobiliQrCodeRelativePath(string $externalId): string
    {
        $externalId = trim($externalId);

        return $externalId === '' ? '' : 'immobili/'.$externalId.'/qrcode.png';
    }
}

if (!function_exists('immobiliQrCodeUrl')) {
    /**
     * URL pubblico del QR dell'immobile (o '' se non disponibile). Usato in
     * lettura da `Immobile::decorate()`, dai cartelli PDF e dal backend.
     */
    function immobiliQrCodeUrl(string $externalId): string
    {
        $relative = immobiliQrCodeRelativePath($externalId);

        if ($relative === '') {
            return '';
        }

        $path = $GLOBALS['PATH'] ?? null;
        $base = is_object($path) ? rtrim((string) ($path->upload ?? ''), '/') : '';

        return $base === '' ? '' : $base.'/'.$relative;
    }
}

if (!function_exists('immobiliQrCodeFile')) {
    /**
     * Path su filesystem del QR dell'immobile (o null): destinazione della
     * generazione al sync. Speculare a immobiliQrCodeUrl() sulla root di upload.
     */
    function immobiliQrCodeFile(string $externalId): ?string
    {
        $relative = immobiliQrCodeRelativePath($externalId);

        if ($relative === '') {
            return null;
        }

        $path = $GLOBALS['PATH'] ?? null;
        $base = is_object($path) ? rtrim((string) ($path->rUpload ?? ''), '/') : '';

        return $base === '' ? null : $base.'/'.$relative;
    }
}

if (!function_exists('immobiliResolveLocalizedValue')) {
    /**
     * Risolve una struttura potenzialmente localizzata nella lingua richiesta.
     *
     * Se un elemento è un array del tipo ['it' => ..., 'en' => ...] ne estrae la
     * variante per `$locale` (fallback 'it', poi primo valore disponibile).
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    function immobiliResolveLocalizedValue(array $value, ?string $locale = null): array
    {
        $locale = strtolower(trim((string) ($locale ?? 'it'))) ?: 'it';

        $resolved = [];

        foreach ($value as $key => $item) {
            if (is_array($item) && (isset($item['it']) || isset($item['en']))) {
                $resolved[$key] = $item[$locale]
                    ?? $item['it']
                    ?? reset($item);
                continue;
            }

            $resolved[$key] = $item;
        }

        return $resolved;
    }
}
