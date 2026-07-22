<?php

namespace Wonder\Plugin\Immobili\Feed;

/**
 * Value object di configurazione di un feed, costruito da una riga del Model
 * `FeedSource` (che, come tutte le letture del framework, è un array
 * associativo). Dà ai provider/servizi un'API tipizzata e immutabile.
 */
final class FeedSourceConfig
{
    /**
     * @param array<string, mixed> $raw riga originale del Model FeedSource
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $provider,
        public readonly string $code,
        public readonly string $username,
        public readonly string $save_images,
        public readonly string $default_visible,
        public readonly string $default_evidence,
        public readonly string $default_sold,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            provider: (string) ($row['provider'] ?? ''),
            code: (string) ($row['code'] ?? ''),
            username: (string) ($row['username'] ?? ''),
            save_images: (string) ($row['save_images'] ?? ''),
            default_visible: (string) ($row['default_visible'] ?? ''),
            default_evidence: (string) ($row['default_evidence'] ?? ''),
            default_sold: (string) ($row['default_sold'] ?? ''),
            raw: $row,
        );
    }
}
