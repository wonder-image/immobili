# Aggiungere un provider

Per collegare un nuovo gestionale, implementa l'interfaccia `FeedProvider` e registrala.

## 1. Implementa l'interfaccia

```php
namespace App\Immobili;

use Wonder\Plugin\Immobili\Feed\Contracts\FeedProvider;
use Wonder\Plugin\Immobili\Feed\FeedSourceConfig;
use Wonder\Plugin\Immobili\Feed\NormalizedListing;
use Wonder\Plugin\Immobili\Models\Comune;
use Wonder\Plugin\Immobili\Support\Taxonomy;
use Wonder\App\ResourceSchema\FormInput;

final class MioGestionaleProvider implements FeedProvider
{
    public function key(): string   { return 'miogestionale'; }
    public function label(): string { return 'Mio Gestionale'; }

    public function configSchema(): array
    {
        return [ FormInput::key('feed_url')->text()->label('URL feed')->required() ];
    }

    public function syncTaxonomies(FeedSourceConfig $feed): void
    {
        // Upsert delle tassonomie CANONICHE per chiave naturale (cod_catastale,
        // sigla, slug), riempiendo la colonna mappa `miogestionale_id`.
        // Riferimento: GetrixProvider::syncCategorie / syncComuni.
    }

    public function fetchListings(FeedSourceConfig $feed): iterable
    {
        // Le FK tassonomia vanno impostate come ID CANONICO (INT), risolto dal
        // codice/nome nativo:
        //   - con i codici:  Taxonomy::idByProviderCode(Comune::class, $this->key(), $codiceComune)
        //   - con i nomi:    Taxonomy::comuneByName($nomeComune)['id'] ?? 0
        // Se non risolvibile lascia 0/'' (il sync lo salva come NULL) e conserva
        // il nome in `attributi` come fallback per il presenter.
        $listing = new NormalizedListing($externalId);
        $listing->set('prezzo', '250000');
        $listing->set('comune_id', (string) Taxonomy::idByProviderCode(Comune::class, $this->key(), $codiceComune));
        $listing->attribute('comune', $nomeComune); // fallback se non risolto
        $listing->addImage([ 'tipo' => 'F', 'url' => $url, 'medium' => $url ]);
        $listing->addDescription([ 'lingua' => 'it', 'testo' => $descrizione ]);
        yield $listing;
    }
}
```

## 2. Registra il provider

Nel `custom/config/config.php` (o in un boot del sito):

```php
use Wonder\Plugin\Immobili\Feed\ProviderRegistry;

ProviderRegistry::register(new \App\Immobili\MioGestionaleProvider());
```

Da quel momento il gestionale compare nella select del feed in backend.

## Campi utili di `NormalizedListing`

- `set($colonna, $valore)` — colonne canoniche di `immobili` (vedi [Modello immobile](../riferimento/modello-immobile.md)).
- `attribute($chiave, $valore)` — attributi estesi (dotazioni, nomi luogo se non hai codici).
- `addImage([...])`, `addDescription([...])` — righe correlate.
- `evidence` / `sold` — override dei flag se il gestionale li fornisce.
