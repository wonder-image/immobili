<?php

namespace Wonder\Plugin\Immobili\Services;

use Wonder\Plugin\Immobili\Models\Immobile;
use Wonder\Plugin\Immobili\Models\ImmobileDescrizione;
use Wonder\Plugin\Immobili\Models\ImmobileImmagine;
use Wonder\Plugin\Immobili\Support\Taxonomy;

/**
 * Esportatore feed Idealista.
 *
 * Genera il feed XML nel formato di pubblicazione di Idealista (`<ads><ad>…`)
 * a partire dagli immobili pubblicati. Il crawler del portale scarica l'URL
 * dell'endpoint. Il mapping copre i campi principali osservati nel formato di
 * esempio; i codici/tipi specifici vanno rifiniti sulla specifica ufficiale
 * (docs/specifiche/idealista/xml.pdf).
 */
final class IdealistaExporter
{
    /** Mappa lingua → codice lingua Idealista (da verificare sulla specifica). */
    private const LANGUAGES = ['it' => 7, 'en' => 1, 'es' => 5, 'fr' => 2, 'de' => 4];

    public function xml(): string
    {
        $rows = $this->rows(Immobile::find([
            'visible' => 'true',
            'sold'    => 'false',
            'deleted' => 'false',
        ]));

        $out = '<?xml version="1.0" encoding="utf-8"?>'."\n".'<ads>'."\n";

        foreach ($rows as $row) {
            $out .= $this->ad($row);
        }

        $out .= '</ads>'."\n";

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function ad(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $provider = (string) ($row['provider'] ?? '');
        $attributi = immobiliDecodeJsonArray($row['attributi'] ?? []);

        $comune = Taxonomy::comuneNome($provider, (string) ($row['comune_id'] ?? '')) ?: (string) ($attributi['comune'] ?? '');
        $provincia = (string) ($attributi['provincia'] ?? '');
        $affitto = strtoupper((string) ($row['contratto_id'] ?? '')) === 'A';

        $xml = '  <ad>'."\n";
        $xml .= $this->tag('id', (string) (($row['external_id'] ?? '') ?: $id), 2);
        $xml .= $this->tag('reference', (string) ($row['nome'] ?? ''), 2);
        $xml .= $this->tag('operationType', $affitto ? 'rent' : 'sale', 2);
        $xml .= $this->tag('price', (string) ($row['prezzo'] ?? ''), 2);
        $xml .= $this->tag('surfaceArea', (string) ($row['superficie'] ?? ''), 2);
        $xml .= $this->tag('roomNumber', (string) ($row['n_camere'] ?? ''), 2);
        $xml .= $this->tag('bathNumber', (string) ($row['n_bagni'] ?? ''), 2);

        $classe = (string) ($row['classe_energetica'] ?? '');
        if ($classe !== '') {
            $xml .= '    <energyCertification>'."\n";
            $xml .= $this->tag('rating', $classe, 3);
            $xml .= '    </energyCertification>'."\n";
        }

        // Indirizzo
        $xml .= '    <address>'."\n";
        $xml .= $this->tag('streetName', (string) ($row['strada'] ?? ''), 3);
        $xml .= $this->tag('streetNumber', (string) ($row['civico'] ?? ''), 3);
        $xml .= $this->tag('postalCode', (string) ($row['cap'] ?? ''), 3);
        $xml .= $this->tag('town', $comune, 3);
        $xml .= $this->tag('province', $provincia, 3);
        $xml .= $this->tag('latitude', (string) ($row['latitudine'] ?? ''), 3);
        $xml .= $this->tag('longitude', (string) ($row['longitudine'] ?? ''), 3);
        $xml .= $this->tag('visibility', immobiliIsTrue($row['pub_indirizzo'] ?? 'true') ? 'street' : 'none', 3);
        $xml .= '    </address>'."\n";

        // Descrizioni multilingua
        $comments = $this->comments($id);
        if ($comments !== '') {
            $xml .= '    <comments>'."\n".$comments.'    </comments>'."\n";
        }

        // Immagini
        $pictures = $this->pictures($id);
        if ($pictures !== '') {
            $xml .= '    <multimedias>'."\n".$pictures.'    </multimedias>'."\n";
        }

        $xml .= '  </ad>'."\n";

        return $xml;
    }

    private function comments(int $immobileId): string
    {
        $out = '';

        foreach ($this->rows(ImmobileDescrizione::find(['immobile_id' => $immobileId])) as $row) {
            $testo = trim((string) ($row['testo'] ?? ''));
            if ($testo === '') {
                continue;
            }

            $lingua = strtolower((string) ($row['lingua'] ?? 'it'));
            $code = self::LANGUAGES[$lingua] ?? self::LANGUAGES['it'];

            $out .= '      <adComments>'."\n";
            $out .= $this->tag('propertyComment', $testo, 4);
            $out .= $this->tag('language', (string) $code, 4);
            $out .= '      </adComments>'."\n";
        }

        return $out;
    }

    private function pictures(int $immobileId): string
    {
        $out = '';

        foreach ($this->rows(ImmobileImmagine::find(['immobile_id' => $immobileId], null, 'position', 'ASC')) as $row) {
            $url = $this->imageUrl($row);
            if ($url === '') {
                continue;
            }

            $out .= '      <pictures>'."\n";
            $out .= $this->tag('multimediaPath', $url, 4);
            $out .= $this->tag('position', (string) ($row['position'] ?? ''), 4);
            if (immobiliIsTrue($row['planimetria'] ?? '')) {
                $out .= $this->tag('tag', 'plan', 4);
            }
            $out .= '      </pictures>'."\n";
        }

        return $out;
    }

    /**
     * URL a massima risoluzione disponibile per un'immagine.
     *
     * @param array<string, mixed> $row
     */
    private function imageUrl(array $row): string
    {
        $source = trim((string) ($row['source_url'] ?? ''));
        if ($source !== '') {
            return $source;
        }

        $path = $GLOBALS['PATH'] ?? null;
        $base = is_object($path) ? rtrim((string) ($path->upload ?? ''), '/') : '';
        if ($base === '') {
            return '';
        }

        $upload = trim((string) ($row['upload'] ?? ''));
        if ($upload !== '') {
            return $base.'/immobili/'.$upload;
        }

        $file = trim((string) ($row['file'] ?? ''));
        if ($file !== '') {
            return $base.'/'.$file;
        }

        return '';
    }

    private function tag(string $name, string $value, int $indent): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_repeat('  ', $indent).'<'.$name.'>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</'.$name.'>'."\n";
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return isset($rows['id']) ? [$rows] : array_values($rows);
    }
}
