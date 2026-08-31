# Installazione

Il modulo richiede un progetto basato su **wonder-image/new-site** (con `wonder-image/app` installato).

## 1. Installa il pacchetto

```bash
composer require wonder-image/immobili
```

## 2. Abilita il modulo

In `custom/config/modules.php` del sito:

```php
return [
    'immobili' => [
        'enabled' => true,
    ],
];
```

## 3. Crea le tabelle

Dal root del sito:

```bash
php forge update --local
```

Vengono create le tabelle `immobili`, `immobili_immagini`, `immobili_descrizioni`, `immobili_feed`
e le tassonomie (`immobili_categorie`, `immobili_tipologie`, `immobili_comuni`, …).

## 4. Verifica

```bash
php forge status:modules
```

`immobili` deve risultare abilitato.

## 5. Configura un feed

Apri il backend → sezione **Immobili → Feed**, aggiungi un feed (provider + credenziali) e premi
**Sincronizza ora**. Vedi [Feed e gestionali](../configurazione/feed-e-gestionali.md).

Il frontend è disponibile su `/immobili/` (lista), `/immobili/{slug}/` (dettaglio),
`/immobili/venduti/` (venduti).

## 6. Verifica la CLI e configura i cron

Il comando seguente usa il file reale installato dal modulo e non richiede il link `vendor/bin`:

```bash
php vendor/wonder-image/immobili/bin/immobili list
```

Deve elencare `sync` e `images`. Configura quindi i due task separati seguendo
[API, CLI e sincronizzazione](../riferimento/api-e-sync.md#creare-i-cron): CLI con path assoluto se
il cron gira sul server del sito, endpoint HTTP con token soltanto per scheduler esterni.
