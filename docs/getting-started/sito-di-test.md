# Sito di test (boilerplate)

Per provare il modulo esiste un sito di test dedicato, `immobili-site`, sul modello di `rsvp-site`,
**basato su `wonder-image/new-site`**.

Percorso: `boilerplates/immobili-site` (accanto a `boilerplates/rsvp-site`).

## Com'è costruito

Copia di `wonder-image/new-site` con il modulo agganciato via **path repository** (symlink al
pacchetto locale) e abilitato in `custom/config/modules.php`:

```json
// composer.json
"repositories": [
    { "type": "path", "url": "../../packages/immobili", "options": { "symlink": true } }
],
"require": { "wonder-image/app": "dev-main", "wonder-image/immobili": "@dev" }
```

```php
// custom/config/modules.php
return [ 'immobili' => [ 'enabled' => true ] ];
```

## Verificare l'integrazione (senza DB)

```bash
cd boilerplates/immobili-site
php forge status:modules      # immobili → enabled: true, valid: true
```

L'autoloader del sito registra `Wonder\Plugin\Immobili\` e carica Model, Resource ed entrypoint.
Con il path repo simlinkato il modulo può comparire due volte in `status:modules` (sorgente
`composer` e `vendor`): è normale, il Registry deduplica per priorità.

## Esecuzione completa

Richiede un database MySQL e un dominio locale (Herd). I passi (dettaglio in
`boilerplates/immobili-site/README.md`):

1. Crea DB/utente coerenti con `.env` (`main:immobili`).
2. Servi `immobili.test` con Herd.
3. `php forge update --local` → crea le tabelle `immobili*`.
4. `php forge start` (o Herd).
5. Seed dati: apri `https://immobili.test/api/immobili/seed/`.
6. Genera le immagini webp: `https://immobili.test/api/immobili/images/?token=...`.
7. Visita `https://immobili.test/immobili/` (it/en), il dettaglio, i venduti, il PDF e il backend.

## Creare un nuovo sito immobiliare da zero

Per un progetto reale (non di test), parti da `wonder-image/new-site`, poi:

```bash
composer require wonder-image/immobili
```

e abilita il modulo in `custom/config/modules.php`. Vedi [Installazione](installazione.md).
