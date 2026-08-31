# Permessi e ruoli

Il modulo definisce due permessi (in `config/permissions.php`):

- **`immobili_manager`** (area `backend`): per chi gestisce feed e immobili senza essere
  amministratore completo.
- **`immobili_sync`** (area `api`): authority dell'utente API dedicato `@immobili`, usato
  esclusivamente per autenticare gli endpoint di sincronizzazione. Non è un ruolo da assegnare
  a persone: il modulo crea l'utente e il token automaticamente.

## Accesso alle sezioni

Le Resource `FeedSourceResource` e `ImmobileResource` sono accessibili a chi ha autorità `admin`
oppure `immobili_manager`:

```php
PermissionSchema::for(static::class)
    ->backend(['list', 'edit', 'update', 'delete'], ['admin', 'immobili_manager']);
```

## Assegnare il ruolo

Assegna il permesso `immobili_manager` a un ruolo/utente dal backend di gestione utenti del sito
(sezione ruoli). Un utente con questo permesso vede la sezione **Immobili** (Feed + Immobili) ma non
il resto dell'amministrazione.

## Endpoint di sincronizzazione

Gli endpoint `/api/immobili/{sync,images}/` sono protetti dal token dell'utente API `@immobili`
(authority `immobili_sync`): gli scheduler HTTP lo inviano in header Bearer, Gestim (push) come
`?token=…`. La CLI locale non passa da questi endpoint. Nessuna variabile d'ambiente. Vedi
[API, CLI e sincronizzazione](../riferimento/api-e-sync.md).
