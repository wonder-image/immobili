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

## 6. Controlla le pianificazioni

Non ci sono cron da configurare: `php forge update --local` registra nello scheduler del framework
le attività `immobili.sync` e `immobili.images`, attive per default. Verificale in
**Backend → Dev → Automazioni → Pianificazioni** (serve il cron unico di wonder-image/app già
attivo sul server). Dettagli in
[Pianificazioni](../riferimento/api-e-sync.md#pianificazioni).

Per lanciare a mano gli stessi lavori resta la CLI del modulo:

```bash
php vendor/wonder-image/immobili/bin/immobili list
```
