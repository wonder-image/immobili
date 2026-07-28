# Provider Gestim

Gestim funziona in **push**: è il gestionale a chiamare l'endpoint di sincronizzazione del sito,
passando in `callback` l'URL dello ZIP del feed. Il modulo riusa l'SDK `Wonder\Plugin\Gestim\Import`.

## Configurazione del feed

Gestim **non** richiede né URL feed né password: tutto arriva nello ZIP inviato in push.

| Campo               | Valore         |
| ------------------- | -------------- |
| Gestionale          | Gestim         |
| Codice / ID Agenzia | `society_id`   |
| ID Sito             | `site_id`      |

## Configurare il push su Gestim

Nel pannello Gestim imposta come URL di notifica (il token va in query perché Gestim non
può impostare header HTTP):

```
https://tuosito.it/api/immobili/sync/?feed=<ID_FEED>&token=<TOKEN>
```

Gestim aggiungerà automaticamente `&callback=<url-zip>` alla chiamata. `<ID_FEED>` è l'id della riga
feed nel backend; `<TOKEN>` è il token dell'utente API dedicato `@immobili`, mostrato come testo
secondario nel pannello del feed (vedi [API e sincronizzazione](../riferimento/api-e-sync.md)).

## Cosa importa

- **Immobili**: dallo ZIP indicato in `callback` (agenzie + lookup + annunci).
- **Etichette / tipologie**: risolte inline dai lookup Gestim (`LookupValue`, dal `lookup.xml` dello
  ZIP). Non esistendo un file tipologie separato, la **tipologia canonica si aggancia per nome** alle
  righe esistenti (seminate da Getrix / seed) riempiendo `gestim_id`; su miss il nome resta negli
  attributi.
- **Comune/Provincia/Regione**: forniti come **nomi** (non codici). Il comune si aggancia al canonico
  **per nome** (`Taxonomy::comuneByName`); su miss il nome resta negli attributi e nei campi
  denormalizzati.
- `sold` è derivato dallo stato Gestim (`stato_immo`) ed è autoritativo dal feed.

## Cron (Gestim = push)

Gestim è **push**: chiama lui il tuo URL, quindi **non serve un cron di sincronizzazione**. Serve solo
il cron **immagini** (con il token in header Bearer):

```cron
*/5 * * * * curl -s -H "Authorization: Bearer <TOKEN>" "https://TUOSITO/api/immobili/images/" > /dev/null
```

Vedi la [guida ai cron](../riferimento/api-e-sync.md) per i dettagli.
