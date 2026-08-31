# Sviluppo e test

## Lint

```bash
find src http config view -name '*.php' -exec php -l {} \;
```

## Smoke test

Verifica gli helper puri in isolamento (senza framework):

```bash
php tests/smoke.php
```

## Autoload

Dopo aver aggiunto/spostato classi, dal progetto che possiede i file:

```bash
composer dump-autoload
```

Namespace PSR-4: `Wonder\Plugin\Immobili\` → `src/`.

## Verifica strutturale della CLI

Dalla root del modulo, dopo `composer install`, puoi controllare registrazione e opzioni senza
avviare il database o una sincronizzazione:

```bash
php bin/immobili list --raw
php bin/immobili sync --help
php bin/immobili images --help
```

In un sito installato verifica anche il percorso destinato ai cron:

```bash
php /PERCORSO/SITO/vendor/wonder-image/immobili/bin/immobili list --raw
```

L'esecuzione reale di `sync` e `images` avvia invece il bootstrap completo del sito e richiede
configurazione e database disponibili.

## Provare in un sito

1. In un progetto `wonder-image/new-site`, aggiungi il pacchetto (path repository o require).
2. Abilita il modulo in `custom/config/modules.php`.
3. `php forge update --local` (crea le tabelle) e `php forge start`.
4. `php forge status:modules` per confermare che `immobili` è abilitato.
5. Configura un feed dal backend e premi **Sincronizza ora**.
6. Verifica `php vendor/wonder-image/immobili/bin/immobili list --raw`.
7. Visita `/immobili/`, `/immobili/{slug}/`, `/immobili/venduti/` in italiano e inglese.

## Struttura del codice

- Logica di dominio nei **provider** (`Feed/`) e nei servizi di reparto (`Catalog/`, `Sync/`, `Media/`, `Seeding/`, `Export/`); le view restano
  sottili e usano `ImmobilePresenter` / `ImmobileQuery`.
- I **Model** definiscono lo schema (SQL + dati); le **Resource** il backend CRUD.
- Aggiungere un gestionale = un nuovo `FeedProvider` (vedi
  [Aggiungere un provider](../provider/aggiungere-un-provider.md)).
