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

## Provare in un sito

1. In un progetto `wonder-image/new-site`, aggiungi il pacchetto (path repository o require).
2. Abilita il modulo in `custom/config/modules.php`.
3. `php forge update --local` (crea le tabelle) e `php forge start`.
4. `php forge status:modules` per confermare che `immobili` è abilitato.
5. Configura un feed dal backend e premi **Sincronizza ora**.
6. Visita `/immobili/`, `/immobili/{slug}/`, `/immobili/venduti/` in italiano e inglese.

## Struttura del codice

- Logica di dominio nei **provider** (`Feed/`) e nei **servizi** (`Services/`); le view restano
  sottili e usano `ImmobilePresenter` / `ImmobileQuery`.
- I **Model** definiscono lo schema (SQL + dati); le **Resource** il backend CRUD.
- Aggiungere un gestionale = un nuovo `FeedProvider` (vedi
  [Aggiungere un provider](../provider/aggiungere-un-provider.md)).
